<?php

namespace App\Services\Agent;

/**
 * Converts an OpenAI chat-completions payload (what the agent loop builds) into the
 * Anthropic Messages format for TokenomixAI /v1/messages.
 *
 * Claude models get tool calls and tool results as native tool_use / tool_result
 * blocks this way, instead of relying on the gateway's OpenAI -> Anthropic translation
 * (which can lose tool results: the model then says the tool output never arrived).
 */
class AnthropicFormat
{
    public static function toMessagesPayload(array $payload, int $defaultMaxTokens = 16384): array
    {
        $messages = MessageSanitizer::sanitize((array) ($payload['messages'] ?? []));

        $system = [];
        $out = [];
        $push = function (string $role, array $blocks) use (&$out) {
            if (!$blocks) {
                return;
            }
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last]['role'] === $role) {
                // Same role twice: one turn with all blocks (tool results stay first)
                $out[$last]['content'] = array_merge($out[$last]['content'], $blocks);
                return;
            }
            $out[] = ['role' => $role, 'content' => $blocks];
        };

        foreach ($messages as $m) {
            $role = $m['role'];
            $text = is_string($m['content'] ?? null) ? $m['content'] : '';

            if ($role === 'system') {
                $system[] = $text;
            } elseif ($role === 'user') {
                $push('user', [['type' => 'text', 'text' => $text]]);
            } elseif ($role === 'tool') {
                $push('user', [['type' => 'tool_result', 'tool_use_id' => $m['tool_call_id'], 'content' => $text]]);
            } elseif ($role === 'assistant') {
                $blocks = [];
                if (trim($text) !== '') {
                    $blocks[] = ['type' => 'text', 'text' => rtrim($text)];
                }
                foreach ((array) ($m['tool_calls'] ?? []) as $call) {
                    $input = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => $call['id'],
                        'name' => $call['function']['name'],
                        'input' => is_array($input) && $input !== [] ? $input : new \stdClass(),
                    ];
                }
                $push('assistant', $blocks);
            }
        }

        // The first turn must come from the user
        if ($out && $out[0]['role'] !== 'user') {
            array_unshift($out, ['role' => 'user', 'content' => [['type' => 'text', 'text' => '(lanjutkan)']]]);
        }

        $result = [
            'model' => $payload['model'],
            'max_tokens' => (int) ($payload['max_tokens'] ?? 0) ?: $defaultMaxTokens,
            'messages' => $out,
            'stream' => true,
        ];
        if ($system) {
            $result['system'] = implode("\n\n", $system);
        }
        if (isset($payload['temperature'])) {
            $result['temperature'] = min(1.0, (float) $payload['temperature']);
        }
        if (!empty($payload['tools'])) {
            $result['tools'] = array_map(fn ($t) => [
                'name' => $t['function']['name'],
                'description' => $t['function']['description'] ?? '',
                'input_schema' => $t['function']['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
            ], $payload['tools']);
            $result['tool_choice'] = ['type' => 'auto'];
        }

        return $result;
    }

    /**
     * Anthropic stop_reason -> OpenAI finish_reason used by the agent loop.
     */
    public static function finishReason(?string $stopReason): ?string
    {
        return match ($stopReason) {
            'tool_use' => 'tool_calls',
            'max_tokens' => 'length',
            'end_turn', 'stop_sequence' => 'stop',
            null => null,
            default => $stopReason,
        };
    }
}
