<?php

namespace App\Services\Agent;

/**
 * Makes an OpenAI-style message list safe to send through TokenomixAI to any provider.
 *
 * Anthropic models behind the gateway reject (HTTP 400) shapes OpenAI tolerates:
 * tool-call arguments that are not a JSON object, tool results without their call (or the
 * reverse), empty text turns, two turns of the same role in a row, odd tool ids, and
 * invalid UTF-8. These are repaired here right before every request.
 */
class MessageSanitizer
{
    public static function sanitize(array $messages): array
    {
        $out = [];
        $openCalls = [];   // tool_call ids still waiting for a result
        $idMap = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? '';
            $content = isset($m['content']) && $m['content'] !== null ? self::cleanText((string) $m['content']) : null;

            if ($role === 'tool') {
                $id = $idMap[$m['tool_call_id'] ?? ''] ?? null;
                if ($id === null || !isset($openCalls[$id])) {
                    continue; // orphan result: its call is gone
                }
                unset($openCalls[$id]);
                $out[] = ['role' => 'tool', 'tool_call_id' => $id, 'content' => $content !== null && trim($content) !== '' ? $content : '(hasil kosong)'];
                continue;
            }

            // A new non-tool turn closes any call that never got a result
            $out = self::closeOpenCalls($out, $openCalls);

            if ($role === 'assistant' && !empty($m['tool_calls'])) {
                $calls = [];
                foreach ((array) $m['tool_calls'] as $n => $call) {
                    $name = (string) ($call['function']['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $origId = (string) ($call['id'] ?? '');
                    $id = preg_replace('/[^A-Za-z0-9_-]/', '_', $origId);
                    if ($id === '' || isset($idMap[$origId]) && $idMap[$origId] !== $id) {
                        $id = 'call_' . $n . '_' . substr(md5($origId . $name . count($out)), 0, 8);
                    }
                    $idMap[$origId] = $id;
                    $openCalls[$id] = true;
                    $calls[] = [
                        'id' => $id,
                        'type' => 'function',
                        'function' => ['name' => $name, 'arguments' => self::cleanArguments((string) ($call['function']['arguments'] ?? ''))],
                    ];
                }
                if ($calls) {
                    $out[] = ['role' => 'assistant', 'content' => $content !== null && trim($content) !== '' ? rtrim($content) : null, 'tool_calls' => $calls];
                    continue;
                }
            }

            if (!in_array($role, ['system', 'user', 'assistant'], true) || $content === null || trim($content) === '') {
                continue;
            }

            // Same role twice in a row (e.g. a failed answer was skipped): merge them
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last]['role'] === $role && empty($out[$last]['tool_calls']) && $role !== 'system') {
                $out[$last]['content'] .= "\n\n" . $content;
                continue;
            }

            $out[] = ['role' => $role, 'content' => $content];
        }

        $out = self::closeOpenCalls($out, $openCalls);

        // A conversation may not end with assistant text that has trailing whitespace
        $last = count($out) - 1;
        if ($last >= 0 && $out[$last]['role'] === 'assistant' && is_string($out[$last]['content'] ?? null)) {
            $out[$last]['content'] = rtrim($out[$last]['content']);
        }

        return $out;
    }

    /**
     * Smaller fallback after a 400: system prompt, a short slice of older chat, and the current
     * request with old tool results and file contents cut down.
     */
    public static function reduce(array $messages, int $keepHistory = 4): array
    {
        $system = array_values(array_filter($messages, fn ($m) => ($m['role'] ?? '') === 'system'));
        $rest = array_values(array_filter($messages, fn ($m) => ($m['role'] ?? '') !== 'system'));

        // Start of the current run = last plain user message
        $start = 0;
        foreach ($rest as $i => $m) {
            if (($m['role'] ?? '') === 'user') {
                $start = $i;
            }
        }
        $history = array_slice(array_slice($rest, 0, $start), -$keepHistory);
        $history = array_map(fn ($m) => isset($m['content']) && is_string($m['content'])
            ? ['role' => $m['role'], 'content' => self::shorten($m['content'], 3000)] + array_diff_key($m, ['content' => 1])
            : $m, $history);

        $current = array_slice($rest, $start);
        $toolIdx = array_keys(array_filter($current, fn ($m) => ($m['role'] ?? '') === 'tool'));
        $recent = array_slice($toolIdx, -2);
        foreach ($current as $i => $m) {
            if (($m['role'] ?? '') === 'tool' && !in_array($i, $recent, true)) {
                $current[$i]['content'] = self::shorten((string) $m['content'], 1200);
            }
            if (!empty($m['tool_calls'])) {
                foreach ($m['tool_calls'] as $k => $call) {
                    $args = (string) ($call['function']['arguments'] ?? '');
                    if (strlen($args) > 2000) {
                        $current[$i]['tool_calls'][$k]['function']['arguments'] = json_encode(
                            ['_ringkas' => 'argumen panjang (isi berkas) sudah dikirim sebelumnya'],
                            JSON_UNESCAPED_UNICODE
                        );
                    }
                }
            }
        }

        return self::sanitize(array_merge($system, $history, $current));
    }

    /**
     * For a model without function calling: turn tool calls/results into plain text turns.
     */
    public static function withoutTools(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'tool') {
                $out[] = ['role' => 'user', 'content' => "Hasil tool:\n" . self::shorten((string) $m['content'], 4000)];
            } elseif (!empty($m['tool_calls'])) {
                $names = implode(', ', array_map(fn ($c) => $c['function']['name'] ?? 'tool', $m['tool_calls']));
                $out[] = ['role' => 'assistant', 'content' => trim(($m['content'] ?? '') . "\n[memanggil tool: {$names}]")];
            } else {
                $out[] = $m;
            }
        }
        return self::sanitize($out);
    }

    /**
     * Shape of a payload for the log: roles, sizes and problems, never the content.
     */
    public static function describe(array $messages): array
    {
        $roles = [];
        $chars = 0;
        $badArgs = 0;
        foreach ($messages as $m) {
            $roles[] = ($m['role'] ?? '?') . (!empty($m['tool_calls']) ? '+tools' : '');
            $chars += strlen((string) ($m['content'] ?? ''));
            foreach ((array) ($m['tool_calls'] ?? []) as $c) {
                $args = (string) ($c['function']['arguments'] ?? '');
                $chars += strlen($args);
                if (!is_array(json_decode($args, true))) {
                    $badArgs++;
                }
            }
        }
        return [
            'messages' => count($messages),
            'chars' => $chars,
            'approx_tokens' => (int) ceil($chars / 3.5),
            'invalid_tool_args' => $badArgs,
            'roles_tail' => implode(' > ', array_slice($roles, -12)),
        ];
    }

    protected static function closeOpenCalls(array $out, array &$openCalls): array
    {
        foreach (array_keys($openCalls) as $id) {
            $out[] = ['role' => 'tool', 'tool_call_id' => $id, 'content' => '(tidak ada hasil: langkah ini terputus)'];
        }
        $openCalls = [];
        return $out;
    }

    protected static function cleanArguments(string $args): string
    {
        $decoded = json_decode($args, true);
        if (is_array($decoded) && !array_is_list($decoded)) {
            return $args;
        }
        if (is_array($decoded) && $decoded === []) {
            return '{}';
        }
        // Truncated or non-object arguments: providers that need a JSON object reject them
        return '{}';
    }

    protected static function cleanText(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        // Control characters (except tab/newline/CR) break some providers
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
    }

    protected static function shorten(string $text, int $max): string
    {
        return strlen($text) > $max ? mb_strcut($text, 0, $max, "UTF-8") . "\n...[diringkas]" : $text;
    }
}
