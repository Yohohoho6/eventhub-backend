# EventHub Backend

Laravel backend for EventHub. It stores events and their sessions and provides
HTTP endpoints for the mobile apps to sync and retrieve event data.

## Features

- Receive event data from the Android app's local SQLite database.
- Store events and related sessions in a Laravel-supported database.
- Provide an endpoint for retrieving events.

## Requirements

- PHP 8.3 or later
- Composer
- SQLite (the default database), or another database supported by Laravel

## Setup

From the project directory, install dependencies and prepare the local
environment:

```bash
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
```

The project defaults to SQLite. To use MySQL or another supported database,
update the `DB_*` values in `.env` before running migrations.

Create the database tables and start the local server:

```bash
php artisan migrate
php artisan serve
```

The server is available at `http://127.0.0.1:8000`.

## API

All responses use JSON.

### Sync events

`POST /api/events/sync`

Send an `events` array. The event name, date, and location are required.

```json
{
  "events": [
    {
      "event_code": "EVT-2026-001",
      "name": "Community Meetup",
      "date": "2026-11-01",
      "location": "Main Hall",
      "organizer": "EventHub",
      "description": "A community networking event",
      "contact_email": "events@example.com",
      "contact_phone": "+1-555-0100",
      "created_at": "2026-10-31 10:00:00",
      "event_sessions": [
        {
          "name": "Opening Talk",
          "category": "Keynote",
          "date": "2026-11-01",
          "start_time": "09:00",
          "end_time": "10:00",
          "room": "Room A",
          "capacity": 100,
          "speaker_name": "Alex Morgan",
          "speaker_role": "Engineer",
          "speaker_email": "alex@example.com",
          "description": "Welcome and opening remarks",
          "skill_level": "Beginner"
        }
      ],
      "sessions": [
        {
          "name": "Opening Talk",
          "category": "Keynote",
          "date": "2026-11-01",
          "start_time": "09:00",
          "end_time": "10:00",
          "room": "Room A",
          "capacity": 100,
          "speaker_name": "Alex Morgan",
          "speaker_role": "Engineer",
          "speaker_email": "alex@example.com",
          "description": "Welcome and opening remarks",
          "skill_level": "Beginner"
        }
      ]
    }
  ]
}
```

The mobile client currently sends both `event_sessions` and `sessions`, with
the same sessions in each array. Include both keys this way to match the current
sync controller. Event session fields other than `name` and `category` are
optional.

Example request:

```bash
curl -X POST http://127.0.0.1:8000/api/events/sync \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"events":[{"event_code":"EVT-2026-001","name":"Community Meetup","date":"2026-11-01","location":"Main Hall"}]}'
```

Successful response:

```json
{
  "status": "success",
  "message": "Successfully synchronized 1 events with Laravel backend."
}
```

An empty or missing `events` array returns HTTP `400`.

### List events

`GET /events`

The endpoint is registered to return a JSON response containing `status` and
`data`. The controller currently eager-loads an `event_sessions` relationship,
while the `Event` model defines the relationship as `sessions`; align those
names before relying on this endpoint.

## Android emulator

The Android emulator can reach a Laravel server running on the development
machine through `10.0.2.2`. The mobile client's default sync URL is:

```text
http://10.0.2.2:8000/api/events/sync
```

For a physical device, use the computer's LAN IP address instead of
`10.0.2.2`, and make sure the device and computer are on the same network.
Configure Laravel to listen on the network interface, for example:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## Tests

Run the test suite with:

```bash
php artisan test
```

The feature tests include coverage for syncing an event without a CSRF token.

## Security

The sync endpoint currently has no authentication middleware. Do not expose it
to the public internet without adding appropriate authentication and access
controls. Keep `.env` and its secrets out of version control.

## License

This project uses the MIT License. See [LICENSE](./LICENSE).
