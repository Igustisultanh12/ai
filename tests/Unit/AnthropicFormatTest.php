<?php

namespace Tests\Unit;

use App\Services\Agent\AnthropicFormat;
use PHPUnit\Framework\TestCase;

class AnthropicFormatTest extends TestCase
{
    public function test_tool_calls_and_results_become_native_blocks(): void
    {
        $out = AnthropicFormat::toMessagesPayload([
            'model' => 'claude-opus-5-5',
            'temperature' => 0.7,
            'messages' => [
                ['role' => 'system', 'content' => 'aturan'],
                ['role' => 'user', 'content' => 'cek CI'],
                ['role' => 'assistant', 'content' => 'Saya cek.', 'tool_calls' => [
                    ['id' => 'toolu_1', 'type' => 'function', 'function' => ['name' => 'github_workflow_runs', 'arguments' => '{"repo":"a/b"}']],
                    ['id' => 'toolu_2', 'type' => 'function', 'function' => ['name' => 'project_list_files', 'arguments' => '{}']],
                ]],
                ['role' => 'tool', 'tool_call_id' => 'toolu_1', 'content' => '{"runs":[]}'],
                ['role' => 'tool', 'tool_call_id' => 'toolu_2', 'content' => '{"files":["a.kt"]}'],
                ['role' => 'user', 'content' => 'lanjutkan'],
            ],
            'tools' => [['type' => 'function', 'function' => ['name' => 'github_workflow_runs', 'description' => 'd', 'parameters' => ['type' => 'object', 'properties' => ['repo' => ['type' => 'string']]]]]],
            'tool_choice' => 'auto',
        ]);

        $this->assertSame('aturan', $out['system']);
        $this->assertSame(16384, $out['max_tokens']);
        $this->assertSame(['user', 'assistant', 'user'], array_column($out['messages'], 'role'));

        $assistant = $out['messages'][1]['content'];
        $this->assertSame('text', $assistant[0]['type']);
        $this->assertSame(['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'github_workflow_runs', 'input' => ['repo' => 'a/b']], $assistant[1]);
        $this->assertInstanceOf(\stdClass::class, $assistant[2]['input']); // {} not []

        // Both results and the follow-up text in one user turn, results first
        $user = $out['messages'][2]['content'];
        $this->assertSame(['tool_result', 'tool_result', 'text'], array_column($user, 'type'));
        $this->assertSame('toolu_2', $user[1]['tool_use_id']);
        $this->assertSame('{"files":["a.kt"]}', $user[1]['content']);

        $this->assertSame('github_workflow_runs', $out['tools'][0]['name']);
        $this->assertArrayHasKey('input_schema', $out['tools'][0]);
        $this->assertSame(['type' => 'auto'], $out['tool_choice']);
        $this->assertNotFalse(json_encode($out));
    }

    public function test_stop_reasons_map_to_finish_reasons(): void
    {
        $this->assertSame('tool_calls', AnthropicFormat::finishReason('tool_use'));
        $this->assertSame('length', AnthropicFormat::finishReason('max_tokens'));
        $this->assertSame('stop', AnthropicFormat::finishReason('end_turn'));
    }
}
