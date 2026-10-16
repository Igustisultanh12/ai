<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ConversationController;
use App\Services\Agent\ChatToolbox;
use App\Services\Agent\MessageSanitizer;
use PHPUnit\Framework\TestCase;

class MessageSanitizerTest extends TestCase
{
    public function test_repairs_shapes_that_providers_reject(): void
    {
        $out = MessageSanitizer::sanitize([
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => 'a'],
            ['role' => 'user', 'content' => 'b'],                                   // same role twice
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [
                ['id' => 'toolu.01:x', 'type' => 'function', 'function' => ['name' => 'github_push_files', 'arguments' => '{"repo":"a/b","files":[{"pa']], // truncated
                ['id' => 'c2', 'type' => 'function', 'function' => ['name' => 'github_list_files', 'arguments' => '{"repo":"a/b"}']],
            ]],
            ['role' => 'tool', 'tool_call_id' => 'toolu.01:x', 'content' => 'ok'],
            // result for c2 is missing
            ['role' => 'tool', 'tool_call_id' => 'ghost', 'content' => 'orphan'],
            ['role' => 'assistant', 'content' => '   '],                             // empty turn
            ['role' => 'user', 'content' => "lanjut\x01"],
        ]);

        $this->assertSame(['system', 'user', 'assistant', 'tool', 'tool', 'user'], array_column($out, 'role'));
        $this->assertSame("a\n\nb", $out[1]['content']);
        $this->assertNull($out[2]['content']);
        $this->assertSame('{}', $out[2]['tool_calls'][0]['function']['arguments']);
        $this->assertSame('toolu_01_x', $out[2]['tool_calls'][0]['id']);
        $this->assertSame('toolu_01_x', $out[3]['tool_call_id']);
        $this->assertSame('c2', $out[4]['tool_call_id']);
        $this->assertSame('lanjut', $out[5]['content']);
        $this->assertNotFalse(json_encode($out));
    }

    public function test_compacted_tool_results_stay_valid_utf8(): void
    {
        $toolbox = (new \ReflectionClass(ChatToolbox::class))->newInstanceWithoutConstructor();
        // Workflow files often contain emoji; cutting them byte-wise broke the request JSON
        $content = json_encode(['files' => [['content' => str_repeat('name: 🚀 Deploy APK ', 400)]]], JSON_UNESCAPED_UNICODE);
        $messages = [['role' => 'user', 'content' => 'x']];
        for ($i = 0; $i < 5; $i++) {
            $messages[] = ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => "c{$i}", 'type' => 'function', 'function' => ['name' => 'github_read_files', 'arguments' => '{}']]]];
            $messages[] = ['role' => 'tool', 'tool_call_id' => "c{$i}", 'content' => $content];
        }

        $compacted = $toolbox->compactHistory($messages);

        $this->assertLessThan(strlen($content), strlen($compacted[2]['content']));
        $this->assertTrue(mb_check_encoding($compacted[2]['content'], 'UTF-8'));
        $this->assertNotFalse(json_encode($compacted));
        $this->assertNotFalse(json_encode(MessageSanitizer::reduce($compacted)));
    }

    public function test_reduce_keeps_current_run_and_shortens_old_results(): void
    {
        $messages = [['role' => 'system', 'content' => 'sys']];
        for ($i = 0; $i < 10; $i++) {
            $messages[] = ['role' => 'user', 'content' => "old {$i}"];
            $messages[] = ['role' => 'assistant', 'content' => str_repeat('x', 5000)];
        }
        $messages[] = ['role' => 'user', 'content' => 'now'];
        for ($i = 0; $i < 4; $i++) {
            $messages[] = ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => "c{$i}", 'type' => 'function', 'function' => ['name' => 't', 'arguments' => json_encode(['files' => str_repeat('y', 5000)])]]]];
            $messages[] = ['role' => 'tool', 'tool_call_id' => "c{$i}", 'content' => str_repeat('z', 5000)];
        }

        $out = MessageSanitizer::reduce($messages);
        $user = array_values(array_filter($out, fn ($m) => $m['role'] === 'user'));

        $this->assertSame('now', end($user)['content']);
        $this->assertLessThanOrEqual(3, count($user));
        $tools = array_values(array_filter($out, fn ($m) => $m['role'] === 'tool'));
        $this->assertCount(4, $tools);
        $this->assertLessThan(1300, strlen($tools[0]['content']));
        $this->assertSame(5000, strlen($tools[3]['content']));
        $this->assertLessThan(200, strlen($out[array_key_last($out) - 1]['tool_calls'][0]['function']['arguments']));
    }

    public function test_generic_400_hint_does_not_disable_tools(): void
    {
        $generic = 'Request rejected by AI provider (HTTP 400). Other possible cause: an unsupported request shape — unusual parameters, a malformed tool/function schema.';
        $this->assertFalse(ConversationController::saysToolsUnsupported($generic));
        $this->assertTrue(ConversationController::saysToolsUnsupported('This model does not support tools'));
        $this->assertTrue(ConversationController::saysToolsUnsupported('function calling is not supported for this model'));
    }
}
