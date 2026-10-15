<?php

namespace App\Services\Agent;

/**
 * Rebuilds OpenAI-style tool calls from streamed deltas.
 *
 * Gateways differ in how they stream tool calls (TokenomixAI fronts several
 * providers): some send `index` on every chunk (OpenAI), some only on the first
 * chunk, some repeat the function name, and some resend the complete arguments
 * at the end. All of these must end up as one call with the full arguments.
 */
class ToolCallAccumulator
{
    /** @var array<int, array{id: string, type: string, function: array{name: string, arguments: string}}> */
    protected array $calls = [];

    protected ?int $last = null;

    public function add(array $deltas): void
    {
        foreach ($deltas as $tc) {
            if (!is_array($tc)) {
                continue;
            }

            $id = (string) ($tc['id'] ?? '');
            $i = $this->slotFor($tc, $id);

            $this->calls[$i] ??= ['id' => '', 'type' => 'function', 'function' => ['name' => '', 'arguments' => '']];
            $call = &$this->calls[$i];

            if ($id !== '') {
                $call['id'] = $id;
            }

            $name = $tc['function']['name'] ?? null;
            if (is_string($name) && $name !== '') {
                // Some providers repeat the full name on every chunk; others split it
                if ($call['function']['name'] === '' || $call['function']['name'] === $name) {
                    $call['function']['name'] = $name;
                } elseif (!str_ends_with($call['function']['name'], $name)) {
                    $call['function']['name'] .= $name;
                }
            }

            if (array_key_exists('arguments', $tc['function'] ?? [])) {
                $piece = $tc['function']['arguments'];
                if (!is_string($piece)) {
                    $piece = json_encode($piece, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                $current = $call['function']['arguments'];

                if ($current !== '' && $this->isCompleteJson($current) && $this->isCompleteJson($piece)) {
                    // Complete arguments resent after the deltas: keep the fuller one
                    if (strlen($piece) >= strlen($current)) {
                        $call['function']['arguments'] = $piece;
                    }
                } else {
                    $call['function']['arguments'] .= $piece;
                }
            }

            unset($call);
            $this->last = $i;
        }
    }

    /**
     * @return array<int, array{id: string, type: string, function: array{name: string, arguments: string}}>
     */
    public function calls(): array
    {
        ksort($this->calls);
        $calls = array_values(array_filter($this->calls, fn ($c) => $c['function']['name'] !== ''));

        foreach ($calls as $n => &$c) {
            if ($c['id'] === '') {
                $c['id'] = 'call_' . $n . '_' . bin2hex(random_bytes(4));
            }
        }
        unset($c);

        return $calls;
    }

    public function argumentChars(): int
    {
        return array_sum(array_map(fn ($c) => strlen($c['function']['arguments']), $this->calls));
    }

    public function lastName(): ?string
    {
        $name = null;
        foreach ($this->calls as $c) {
            $name = $c['function']['name'] ?: $name;
        }
        return $name;
    }

    protected function slotFor(array $tc, string $id): int
    {
        // A known id always goes back to its own call
        if ($id !== '') {
            foreach ($this->calls as $i => $c) {
                if ($c['id'] === $id) {
                    return $i;
                }
            }
        }

        if (isset($tc['index']) && is_numeric($tc['index'])) {
            $i = (int) $tc['index'];
            // Same index reused for a different call (new id): open a new slot
            if ($id !== '' && isset($this->calls[$i]) && $this->calls[$i]['id'] !== '' && $this->calls[$i]['id'] !== $id) {
                return $this->nextSlot();
            }
            return $i;
        }

        // No index: a chunk with a new id or name starts a call, anything else continues the last one
        $startsNew = $id !== '' || (!empty($tc['function']['name']) && $this->last !== null
            && $this->calls[$this->last]['function']['name'] !== ''
            && $this->calls[$this->last]['function']['name'] !== $tc['function']['name']);

        if ($this->last === null || $startsNew) {
            return $this->last === null ? 0 : $this->nextSlot();
        }

        return $this->last;
    }

    protected function nextSlot(): int
    {
        return empty($this->calls) ? 0 : max(array_keys($this->calls)) + 1;
    }

    protected function isCompleteJson(string $s): bool
    {
        $s = trim($s);
        if ($s === '' || ($s[0] !== '{' && $s[0] !== '[')) {
            return false;
        }
        json_decode($s);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
