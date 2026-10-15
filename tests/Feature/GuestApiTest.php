<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_api_requests_get_json_401_instead_of_login_route_error(): void
    {
        // Same headers as the chat SSE fetch: not a pure JSON request
        $this->post('/api/conversations/abc/stream', ['content' => 'hi'], [
            'Accept' => 'text/event-stream, application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);

        $this->get('/api/conversations', ['Accept' => 'text/html'])->assertStatus(401);
        $this->getJson('/api/models')->assertStatus(401);
    }
}
