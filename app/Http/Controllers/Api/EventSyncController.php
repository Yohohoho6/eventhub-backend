<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventSyncController extends Controller
{
    public function syncEvents(Request $request): JsonResponse
    {
        $incomingEvents = $request->input('events', []);

        if (empty($incomingEvents)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No events provided in payload.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $syncedCount = 0;

            foreach ($incomingEvents as $eventData) {
                $eventAttributes = [
                    'event_code' => $eventData['event_code'] ?? null,
                    'name' => $eventData['name'],
                    'date' => $eventData['date'],
                    'location' => $eventData['location'],
                    'organizer' => $eventData['organizer'] ?? null,
                    'description' => $eventData['description'] ?? null,
                    'contact_email' => $eventData['contact_email'] ?? null,
                    'contact_phone' => $eventData['contact_phone'] ?? null,
                    'created_at_local' => $eventData['created_at'] ?? null,
                    'photo_path' => $eventData['photo_path'] ?? $eventData['photoPath'] ?? null,
                ];

                $event = $eventAttributes['event_code'] !== null
                    ? Event::updateOrCreate(
                        ['event_code' => $eventAttributes['event_code']],
                        $eventAttributes,
                    )
                    : Event::create($eventAttributes);

                $syncedCount++;

                $sessions = $eventData['event_sessions'] ?? $eventData['sessions'] ?? [];

                $event->sessions()->delete();

                if (is_array($sessions)) {
                    foreach ($sessions as $sessionData) {
                        $event->sessions()->create([
                            'name' => $sessionData['name'],
                            'category' => $sessionData['category'],
                            'date' => $sessionData['date'] ?? null,
                            'start_time' => $sessionData['start_time'] ?? null,
                            'end_time' => $sessionData['end_time'] ?? null,
                            'room' => $sessionData['room'] ?? null,
                            'capacity' => $sessionData['capacity'] ?? 0,
                            'speaker_name' => $sessionData['speaker_name'] ?? null,
                            'speaker_role' => $sessionData['speaker_role'] ?? null,
                            'speaker_email' => $sessionData['speaker_email'] ?? null,
                            'description' => $sessionData['description'] ?? null,
                            'skill_level' => $sessionData['skill_level'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Successfully synchronized {$syncedCount} events with Laravel backend.",
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: '.$e->getMessage(),
            ], 500);
        }
    }

    // Optional: Endpoint for Hybrid User App (Feature g) to fetch events
    public function getEvents(): JsonResponse
    {
        $events = Event::with('sessions')->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $events,
        ], 200);
    }
}
