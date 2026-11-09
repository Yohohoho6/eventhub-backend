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
            'photo_path' => null,
        ]);
    }

    public function test_mobile_sync_endpoint_persists_optional_photo_path_and_returns_it(): void
    {
        $photoPath = '/storage/emulated/0/EventHub/event-banner.jpg';

        $response = $this->postJson('/api/events/sync', [
            'events' => [
                [
                    'event_code' => 'EVT-2026-002',
                    'name' => 'Photo Meetup',
                    'date' => '2026-11-02',
                    'location' => 'Community Hall',
                    'photo_path' => $photoPath,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('events', [
            'event_code' => 'EVT-2026-002',
            'photo_path' => $photoPath,
        ]);

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonPath('data.0.photo_path', $photoPath);
    }
}
