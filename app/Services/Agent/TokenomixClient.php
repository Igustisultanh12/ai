<?php

namespace App\Services\Agent;

use App\Models\AgentConfig;
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
     * Stream chat completion with SSE parsing.
     * Invokes $callback($chunkText, $isDone, $usageData) for each incoming event.
     */
    public function streamChatCompletion(array $payload, callable $callback): void
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
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config?->timeout_seconds ?: 90);

        $buffer = '';
        $streamStarted = false;

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curlHandle, $data) use (&$buffer, &$streamStarted, $callback) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (empty($line)) {
                    continue;
                }

                if (str_starts_with($line, 'data:')) {
                    $jsonStr = trim(substr($line, 5));
                    if ($jsonStr === '[DONE]') {
                        $callback('', true, null);
                        return strlen($data);
                    }

                    $decoded = json_decode($jsonStr, true);
                    if (is_array($decoded)) {
                        $streamStarted = true;
                        $chunkContent = $decoded['choices'][0]['delta']['content'] ?? '';
                        $usage = $decoded['usage'] ?? null;
                        $callback($chunkContent, false, $usage);
                    }
                }
            }
            return strlen($data);
        });

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!$success || $httpCode >= 400) {
            if (!$streamStarted) {
                throw new Exception("Streaming TokenomixAI gagal dengan kode HTTP {$httpCode}: {$curlError}");
            }
        }
    }

    /**
     * Map HTTP error codes and body to actionable Indonesian messages per PDF specs.
     */
    protected function handleHttpError($response, int $latency = 0): array
    {
        $status = $response->status();
        $json = $response->json() ?? [];
        $error = $json['error'] ?? [];
        $code = $error['code'] ?? 'unknown_error';
        $message = $error['message'] ?? 'Terjadi kesalahan pada gateway TokenomixAI';

        $actionable = match ($status) {
            401 => 'Autentikasi gagal (401). API key salah, telah di-rotate, atau belum dikonfigurasi.',
            403 => 'Akses ditolak (403). API key dinonaktifkan oleh upstream support. Hubungi admin.',
            404 => ($code === 'model_not_available')
                ? "Model tidak tersedia di TokenomixAI: {$message}. Periksa ID model pada katalog."
                : "Endpoint tidak didukung gateway (404): {$message}",
            429 => "Batas laju permintaan tercapai (429 Too Many Requests). Tunggu " . ($response->header('Retry-After') ?: 'beberapa') . " detik sebelum mencoba lagi.",
            502 => ($code === 'upstream_auth')
                ? "Masalah kredensial upstream pada gateway (502 upstream_auth). Memerlukan tindakan administrator."
                : "Upstream sementara tidak dapat dihubungi (502 upstream_unavailable). Aman untuk dicoba ulang.",
            default => "Error [HTTP {$status}] {$code}: {$message}",
        };

        return [
            'success' => false,
            'status' => 'http_' . $status,
            'code' => $code,
            'message' => $actionable,
            'latency_ms' => $latency,
            'raw_error' => $error,
        ];
    }
}
