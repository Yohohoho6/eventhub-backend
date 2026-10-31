<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_sync_endpoint_accepts_a_request_without_a_csrf_token(): void
    {
        $response = $this->postJson('/api/events/sync', [
            'events' => [
                [
                    'event_code' => 'EVT-2026-001',
                    'name' => 'Community Meetup',
                    'date' => '2026-11-01',
                    'location' => 'Main Hall',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('events', [
            'event_code' => 'EVT-2026-001',
            'name' => 'Community Meetup',
        ]);
    }
}
