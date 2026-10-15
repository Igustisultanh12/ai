<?php

namespace Tests\Unit;

use App\Services\Agent\ChatToolbox;
use Tests\TestCase;

class ChatToolboxCompactTest extends TestCase
{
    public function test_old_tool_results_and_file_arguments_are_shrunk_but_recent_ones_kept(): void
    {
        $big = str_repeat('x', 10000);
        $fileArgs = json_encode(['repo' => 'a/b', 'files' => [['path' => 'a.php', 'content' => $big]]]);

        $messages = [['role' => 'system', 'content' => 'sys'], ['role' => 'user', 'content' => 'hi']];
        for ($i = 0; $i < 5; $i++) {
            $messages[] = ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                ['id' => "c{$i}", 'type' => 'function', 'function' => ['name' => 'github_push_files', 'arguments' => $fileArgs]],
            ]];
            $messages[] = ['role' => 'tool', 'tool_call_id' => "c{$i}", 'content' => $big];
        }

        $out = app(ChatToolbox::class)->compactHistory($messages, 3);
        $tools = array_values(array_filter($out, fn ($m) => $m['role'] === 'tool'));

        $this->assertLessThan(3000, strlen($tools[0]['content']));
        $this->assertLessThan(3000, strlen($tools[1]['content']));
        $this->assertSame($big, $tools[4]['content']);

        $assistants = array_values(array_filter($out, fn ($m) => $m['role'] === 'assistant'));
        $this->assertStringContainsString('isi berkas sudah dikirim, 10000 byte', $assistants[0]['tool_calls'][0]['function']['arguments']);
        $this->assertSame($fileArgs, $assistants[4]['tool_calls'][0]['function']['arguments']);
    }
}
