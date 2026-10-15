<?php

namespace App\Services\Agent;

use App\Models\AgentConfig;
use App\Exceptions\UpstreamRetryableException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenomixClient
{
    protected ?AgentConfig $config = null;
    protected ?string $apiKey = null;
    protected string $baseUrl;

    public function __construct(?AgentConfig $config = null)
    {
        $this->config = $config ?: AgentConfig::where('is_active', true)->first() ?: AgentConfig::first();
        if ($this->config && !empty($this->config->api_key_encrypted)) {
            $this->apiKey = $this->config->api_key_encrypted;
        } else {
            $this->apiKey = config('services.tokenomix.api_key');
        }

        $rawUrl = $this->config?->base_url ?: config('services.tokenomix.base_url', 'https://api.tokenomixai.com/v1');
        // Prevent duplicate /v1/v1
        $this->baseUrl = rtrim($rawUrl, '/');
        if (!str_ends_with($this->baseUrl, '/v1')) {
            $this->baseUrl .= '/v1';
        }
    }

    public function setApiKey(string $key): self
    {
        $this->apiKey = $key;
        return $this;
    }

    public function setBaseUrl(string $url): self
    {
        $clean = rtrim($url, '/');
        if (!str_ends_with($clean, '/v1')) {
            $clean .= '/v1';
        }
        $this->baseUrl = $clean;
        return $this;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Test connection to TokenomixAI gateway.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'message' => 'API Key TokenomixAI belum dikonfigurasi. Harap masukkan API key di menu Agent.',
                'latency_ms' => 0,
            ];
        }

        $start = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(15)->get("{$this->baseUrl}/models");

            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $data = $response->json();
                $modelCount = isset($data['data']) && is_array($data['data']) ? count($data['data']) : 0;
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => "Koneksi berhasil. Terdeteksi {$modelCount} model aktif dari gateway.",
                    'latency_ms' => $latency,
                    'models' => $data['data'] ?? [],
                ];
            }

            return $this->handleHttpError($response, $latency);
        } catch (ConnectionException $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'status' => 'network_error',
                'message' => 'Gagal menghubungi server TokenomixAI. Periksa koneksi internet atau status domain: ' . $e->getMessage(),
                'latency_ms' => $latency,
            ];
        } catch (Exception $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
                'latency_ms' => $latency,
            ];
        }
    }

    /**
     * Fetch active models list from GET /v1/models.
     */
    public function getModels(): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('TokenomixAI API Key belum dikonfigurasi.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->timeout($this->config?->timeout_seconds ?: 30)->get("{$this->baseUrl}/models");

        if ($response->successful()) {
            $json = $response->json();
            return $json['data'] ?? [];
        }

        $error = $this->handleHttpError($response);
        throw new Exception($error['message']);
    }

    /**
     * Send standard non-streaming chat completion request.
     * Retries up to 3 times on 502 upstream_unavailable with exponential backoff & jitter.
     */
    public function chatCompletion(array $payload, int $maxRetries = 3): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('TokenomixAI API Key belum dikonfigurasi.');
        }

        $attempt = 0;
        $start = microtime(true);

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout($this->config?->timeout_seconds ?: 60)
                  ->post("{$this->baseUrl}/chat/completions", $payload);

                $latency = (int) round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $data = $response->json();
                    $data['_latency_ms'] = $latency;
                    $data['_attempt'] = $attempt;
                    return $data;
                }

                // If 502 upstream_unavailable, retry with exponential backoff + jitter
                if ($response->status() === 502) {
                    $json = $response->json();
                    $code = $json['error']['code'] ?? '';
                    if ($code === 'upstream_unavailable' && $attempt < $maxRetries) {
                        $jitter = random_int(100, 500) * 1000; // microseconds
                        $sleepMicros = (int) (pow(2, $attempt) * 1000000 + $jitter);
                        usleep(min($sleepMicros, 5000000));
                        continue;
                    }
                }

                $error = $this->handleHttpError($response, $latency);
                throw new Exception($error['message']);
            } catch (ConnectionException $e) {
                if ($attempt < $maxRetries) {
                    usleep(1000000 * $attempt);
                    continue;
                }
                throw new Exception('Gagal terhubung ke TokenomixAI: ' . $e->getMessage());
            }
        }

        throw new Exception('Batas percobaan request ke upstream tercapai.');
    }

    /**
     * Stream chat completion with SSE parsing (POST /v1/chat/completions, stream: true).
     * Invokes $callback($chunkText, $isDone, $usageData) for each incoming event.
     * Returning false from the callback aborts the upstream request (used for "stop").
     * Returns the assembled tool calls (OpenAI format) and finish reason of the turn.
     * $onTick(int $toolArgChars, ?string $toolName) is called often while the transfer is
     * alive (also when the model is silent or streaming tool arguments), so the caller can
     * keep its own connection to the browser from looking idle.
     *
     * @return array{tool_calls: array, finish_reason: ?string, aborted: bool}
     */
    public function streamChatCompletion(array $payload, callable $callback, ?callable $onTick = null): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('TokenomixAI API Key belum dikonfigurasi.');
        }

        $payload['stream'] = true;
        // Request stream options for usage if supported
        $payload['stream_options'] = ['include_usage' => true];

        $ch = curl_init("{$this->baseUrl}/chat/completions");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        // No hard total limit (long answers / big files can take minutes); abort only when the
        // upstream sends nothing at all for 3 minutes.
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 1);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 180);

        $buffer = '';
        $rawBody = '';
        $streamStarted = false;
        $aborted = false;
        $done = false;
        $retryAfter = null;
        $toolCalls = new ToolCallAccumulator();
        $finishReason = null;

        if ($onTick) {
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_XFERINFOFUNCTION, function () use ($toolCalls, $onTick) {
                $onTick($toolCalls->argumentChars(), $toolCalls->lastName());
                return 0; // keep going
            });
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curlHandle, $header) use (&$retryAfter) {
            if (stripos($header, 'Retry-After:') === 0) {
                $retryAfter = trim(substr($header, 12));
            }
            return strlen($header);
        });

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curlHandle, $data) use (&$buffer, &$rawBody, &$streamStarted, &$aborted, &$done, $toolCalls, &$finishReason, $callback) {
            $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            if ($httpCode >= 400) {
                // Error responses are plain JSON, keep them for the error message.
                if (strlen($rawBody) < 16384) {
                    $rawBody .= $data;
                }
                return strlen($data);
            }

            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || !str_starts_with($line, 'data:')) {
                    continue;
                }

                $jsonStr = trim(substr($line, 5));
                if ($jsonStr === '[DONE]') {
                    if (!$done) {
                        $done = true;
                        $callback('', true, null);
                    }
                    continue;
                }

                $decoded = json_decode($jsonStr, true);
                if (!is_array($decoded)) {
                    continue;
                }

                if (isset($decoded['error'])) {
                    $rawBody = $jsonStr;
                    $aborted = true;
                    return 0;
                }

                $streamStarted = true;

                // Assemble streamed tool calls (delta form, or a full message at the end)
                $toolCalls->add((array) ($decoded['choices'][0]['delta']['tool_calls']
                    ?? $decoded['choices'][0]['message']['tool_calls']
                    ?? []));
                if (!empty($decoded['choices'][0]['finish_reason'])) {
                    $finishReason = $decoded['choices'][0]['finish_reason'];
                }

                $chunkContent = $decoded['choices'][0]['delta']['content'] ?? '';
                $usage = $decoded['usage'] ?? null;
                if ($callback((string) $chunkContent, false, $usage) === false) {
                    $aborted = true;
                    return 0; // abort transfer
                }
            }
            return strlen($data);
        });

        $success = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 400 || ($aborted && $rawBody !== '')) {
            $json = json_decode($rawBody, true) ?: [];
            $error = $json['error'] ?? [];
            $status = $httpCode >= 400 ? $httpCode : 502;
            $code = (string) ($error['code'] ?? 'upstream_error');
            $message = $this->describeError(
                $status,
                $code,
                (string) ($error['message'] ?? ($rawBody ?: 'Tidak ada detail dari gateway.')),
                $retryAfter
            );

            // Per docs: 502 upstream_unavailable is safe to retry; 429 after Retry-After;
            // plain 5xx without a code is treated the same way. upstream_auth is not retryable.
            $retryable = $code === 'upstream_unavailable'
                || $code === 'rate_limit_exceeded'
                || $status === 429
                || ($status >= 500 && !in_array($code, ['upstream_auth'], true) && $code !== 'upstream_error')
                || ($status >= 500 && $code === 'upstream_error' && empty($error['message']));

            if ($retryable) {
                throw new UpstreamRetryableException($message, is_numeric($retryAfter) ? (int) $retryAfter : null);
            }
            throw new Exception($message);
        }

        if ($aborted) {
            return ['tool_calls' => [], 'finish_reason' => 'stop', 'aborted' => true]; // stopped by caller
        }

        if (!$success) {
            // Connection dropped before or in the middle of the answer: a partial answer
            // (possibly with truncated tool arguments) must not be used, retry instead.
            throw new UpstreamRetryableException(
                ($streamStarted ? 'Koneksi ke TokenomixAI terputus di tengah jawaban: ' : 'Gagal terhubung ke TokenomixAI: ')
                . ($curlError ?: "HTTP {$httpCode}")
            );
        }

        if (!$done) {
            $callback('', true, null);
        }

        return ['tool_calls' => $toolCalls->calls(), 'finish_reason' => $finishReason, 'aborted' => false];
    }

    /**
     * Map HTTP error codes and body to actionable Indonesian messages
     * (error format per https://tokenomixai.com/docs: {"error": {message, type, code}}).
     */
    protected function handleHttpError($response, int $latency = 0): array
    {
        $status = $response->status();
        $json = $response->json() ?? [];
        $error = $json['error'] ?? [];
        $code = $error['code'] ?? 'unknown_error';
        $message = $error['message'] ?? 'Terjadi kesalahan pada gateway TokenomixAI';

        return [
            'success' => false,
            'status' => 'http_' . $status,
            'code' => $code,
            'message' => $this->describeError($status, $code, $message, $response->header('Retry-After') ?: null),
            'latency_ms' => $latency,
            'raw_error' => $error,
        ];
    }

    protected function describeError(int $status, string $code, string $message, ?string $retryAfter = null): string
    {
        return match (true) {
            $code === 'invalid_api_key' || $status === 401 => 'Autentikasi gagal (401 invalid_api_key). API key salah, telah di-rotate, atau belum dikonfigurasi.',
            $code === 'key_disabled' || $status === 403 => 'Akses ditolak (403 key_disabled). API key dinonaktifkan, hubungi support TokenomixAI.',
            $code === 'model_not_available' => "Model tidak tersedia di katalog TokenomixAI (404 model_not_available). Pilih model lain. Detail: {$message}",
            $status === 404 => "Endpoint tidak didukung gateway (404): {$message}",
            $code === 'rate_limit_exceeded' || $status === 429 => 'Batas laju permintaan tercapai (429). Tunggu ' . ($retryAfter ?: 'beberapa') . ' detik sebelum mencoba lagi.',
            $code === 'upstream_auth' => 'Masalah kredensial di sisi gateway (502 upstream_auth). Hubungi support TokenomixAI.',
            $code === 'upstream_unavailable' || $status === 502 => 'Upstream sementara tidak dapat dihubungi (502 upstream_unavailable). Aman untuk dicoba ulang.',
            default => "Error [HTTP {$status}] {$code}: {$message}",
        };
    }
}
