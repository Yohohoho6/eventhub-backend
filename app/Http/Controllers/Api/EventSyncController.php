<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $storedPhotoPaths = [];
        $replacedPhotoPaths = [];

        try {
            $syncedCount = 0;

            foreach ($incomingEvents as $eventData) {
                $eventCode = $eventData['event_code'] ?? null;
                $existingEvent = $eventCode === null
                    ? null
                    : Event::where('event_code', $eventCode)->first();
                $uploadedPhotoPath = $this->storeEventPhoto($eventData);

                if ($uploadedPhotoPath !== null) {
                    $storedPhotoPaths[] = $uploadedPhotoPath;

                    if (is_string($existingEvent?->photo_path)
                        && str_starts_with($existingEvent->photo_path, 'events/')) {
                        $replacedPhotoPaths[] = $existingEvent->photo_path;
                    }
                }

                $incomingPhotoPath = $eventData['photo_path'] ?? $eventData['photoPath'] ?? null;
                $existingPhotoPath = $existingEvent?->photo_path;
                $photoPath = $uploadedPhotoPath
                    ?? ($this->isDeviceLocalPhotoPath($incomingPhotoPath) && $existingPhotoPath !== null
                        ? $existingPhotoPath
                        : ($incomingPhotoPath ?? $existingPhotoPath));

                $eventAttributes = [
                    'event_code' => $eventCode,
                    'name' => $eventData['name'],
                    'date' => $eventData['date'],
                    'location' => $eventData['location'],
                    'organizer' => $eventData['organizer'] ?? null,
                    'description' => $eventData['description'] ?? null,
                    'contact_email' => $eventData['contact_email'] ?? null,
                    'contact_phone' => $eventData['contact_phone'] ?? null,
                    'created_at_local' => $eventData['created_at'] ?? null,
                    'photo_path' => $photoPath,
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

            foreach ($replacedPhotoPaths as $replacedPhotoPath) {
                Storage::disk('public')->delete($replacedPhotoPath);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Successfully synchronized {$syncedCount} events with Laravel backend.",
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($storedPhotoPaths as $storedPhotoPath) {
                Storage::disk('public')->delete($storedPhotoPath);
            }

            if ($e instanceof \InvalidArgumentException) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }

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

    public function getEvent(Event $event): JsonResponse
    {
        $event->load('sessions');

        return response()->json([
            'status' => 'success',
            'data' => $event,
        ], 200);
    }

    /**
     * Store a base64 encoded event poster on the public disk.
     *
     * @param  array<string, mixed>  $eventData
     */
    private function storeEventPhoto(array $eventData): ?string
    {
        $photoData = $eventData['photo_data'] ?? null;

        if (! is_string($photoData) || trim($photoData) === '') {
            $photoData = $eventData['photo_base64'] ?? null;
        }

        if ($photoData === null || $photoData === '') {
            return null;
        }

        if (! is_string($photoData)) {
            throw new \InvalidArgumentException('Event photo data must be a base64 string.');
        }

        $photoData = trim($photoData);
        $encodedImage = $photoData;
        $mimeType = null;

        if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/s', $photoData, $matches) === 1) {
            $mimeType = strtolower($matches[1]);
            $encodedImage = $matches[2];
        }

        if (strlen($encodedImage) > 7_000_000) {
            throw new \InvalidArgumentException('Event poster must be 5 MB or smaller.');
        }

        $imageContents = base64_decode($encodedImage, true);

        if ($imageContents === false || strlen($imageContents) > 5_000_000) {
            throw new \InvalidArgumentException('Event photo data is not valid base64 or exceeds 5 MB.');
        }

        $imageInfo = getimagesizefromstring($imageContents);
        $detectedMimeType = $imageInfo['mime'] ?? null;
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if ($detectedMimeType === null || ! isset($extensions[$detectedMimeType])) {
            throw new \InvalidArgumentException('Event poster must be a valid JPEG, PNG, GIF, or WebP image.');
        }

        if ($mimeType !== null && $mimeType !== $detectedMimeType) {
            throw new \InvalidArgumentException('Event photo data MIME type does not match the image.');
        }

        $path = 'events/'.Str::uuid().'.'.$extensions[$detectedMimeType];

        if (! Storage::disk('public')->put($path, $imageContents)) {
            throw new \RuntimeException('Could not store the event poster.');
        }

        return $path;
    }

    private function isDeviceLocalPhotoPath(mixed $photoPath): bool
    {
        return is_string($photoPath)
            && (str_starts_with($photoPath, '/storage/emulated/')
                || str_starts_with($photoPath, '/data/')
                || str_starts_with($photoPath, 'file://'));
    }
}
