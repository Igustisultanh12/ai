<?php

namespace Tests\Unit;

use App\Services\Agent\ToolCallAccumulator;
use PHPUnit\Framework\TestCase;

class ToolCallAccumulatorTest extends TestCase
{
    private function feed(array $chunks): array
    {
        $acc = new ToolCallAccumulator();
        foreach ($chunks as $chunk) {
            $acc->add($chunk);
        }
        return $acc->calls();
    }

    public function test_openai_style_with_index_on_every_chunk(): void
    {
        $calls = $this->feed([
            [['index' => 0, 'id' => 'a', 'function' => ['name' => 'github_push_files', 'arguments' => '']]],
            [['index' => 0, 'function' => ['arguments' => '{"repo":"x/y",']]],
            [['index' => 0, 'function' => ['arguments' => '"files":[]}']]],
        ]);
        $this->assertCount(1, $calls);
        $this->assertSame('{"repo":"x/y","files":[]}', $calls[0]['function']['arguments']);
    }

    public function test_continuation_chunks_without_index_stay_on_the_same_call(): void
    {
        // The bug seen in production: arguments arrived on chunks without "index"
        $calls = $this->feed([
            [['index' => 0, 'id' => 'toolu_1', 'type' => 'function', 'function' => ['name' => 'github_push_files', 'arguments' => '']]],
            [['function' => ['arguments' => '{"repo":"Igustisultanh12/ai",']]],
            [['function' => ['arguments' => '"files":[{"path":"a.vue","content":"<template/>"}]}']]],
        ]);
        $this->assertCount(1, $calls);
        $args = json_decode($calls[0]['function']['arguments'], true);
        $this->assertSame('Igustisultanh12/ai', $args['repo']);
        $this->assertSame('a.vue', $args['files'][0]['path']);
    }

    public function test_no_index_at_all_with_two_calls(): void
    {
        $calls = $this->feed([
            [['id' => 'c1', 'function' => ['name' => 'github_list_files', 'arguments' => '{"repo":']]],
            [['function' => ['arguments' => '"a/b"}']]],
            [['id' => 'c2', 'function' => ['name' => 'github_read_files', 'arguments' => '{"repo":"a/b","paths":["x"]}']]],
        ]);
        $this->assertCount(2, $calls);
        $this->assertSame('{"repo":"a/b"}', $calls[0]['function']['arguments']);
        $this->assertSame('github_read_files', $calls[1]['function']['name']);
    }

    public function test_repeated_name_and_full_arguments_resent_at_end(): void
    {
        $calls = $this->feed([
            [['index' => 0, 'id' => 'x', 'function' => ['name' => 'github_list_files', 'arguments' => '{"repo":"a/b"}']]],
            [['index' => 0, 'id' => 'x', 'function' => ['name' => 'github_list_files', 'arguments' => '{"repo":"a/b"}']]],
        ]);
        $this->assertCount(1, $calls);
        $this->assertSame('github_list_files', $calls[0]['function']['name']);
        $this->assertSame('{"repo":"a/b"}', $calls[0]['function']['arguments']);
    }

    public function test_arguments_sent_as_object(): void
    {
        $calls = $this->feed([
            [['index' => 0, 'id' => 'o', 'function' => ['name' => 'github_list_files', 'arguments' => ['repo' => 'a/b']]]],
        ]);
        $this->assertSame('{"repo":"a/b"}', $calls[0]['function']['arguments']);
    }
}
