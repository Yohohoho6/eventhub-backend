package com.example.eventhub.util

import com.example.eventhub.database.DatabaseHelper
import com.example.eventhub.model.Event
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

data class SyncResult(
    val success: Boolean,
    val syncedCount: Int,
    val message: String
)

object SyncApiClient {

    const val DEFAULT_SERVER_URL = "http://10.0.2.2:8000/api/events/sync"

    /**
     * Builds the exact JSON payload required by the Laravel EventSyncController:
     * {
     *   "events": [
     *     {
     *       "event_code": "EVT-2025-001",
     *       "name": "...",
     *       "date": "...",
     *       "location": "...",
     *       "organizer": "...",
     *       "description": "...",
     *       "contact_email": "...",
     *       "contact_phone": "...",
     *       "created_at": "...",
     *       "event_sessions": [
     *         {
     *           "name": "...",
     *           "category": "...",
     *           "date": "...",
     *           "start_time": "...",
     *           "end_time": "...",
     *           "room": "...",
     *           "capacity": 100,
     *           "speaker_name": "...",
     *           "speaker_role": "...",
     *           "speaker_email": "...",
     *           "description": "...",
     *           "skill_level": "..."
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    fun buildSyncPayload(events: List<Event>, dbHelper: DatabaseHelper): JSONObject {
        val root = JSONObject()
        val eventsArray = JSONArray()

        for (event in events) {
            val eventObj = JSONObject()
            eventObj.put("event_code", event.eventCode ?: "")
            eventObj.put("name", event.name ?: "")
            eventObj.put("date", event.date ?: "")
            eventObj.put("location", event.location ?: "")
            eventObj.put("organizer", event.organizer ?: "")
            eventObj.put("description", event.description ?: "")
            eventObj.put("contact_email", event.contactEmail ?: "")
            eventObj.put("contact_phone", event.contactPhone ?: "")
            eventObj.put("created_at", event.createdAt ?: "")

            // Fetch attached sessions for this event
            val sessionsList = dbHelper.getSessionsForEvent(event.id)
            val sessionsArray = JSONArray()

            for (session in sessionsList) {
                val sessObj = JSONObject()
                sessObj.put("name", session.name ?: "")
                sessObj.put("category", session.category ?: "Keynote")
                sessObj.put("date", session.date ?: event.date ?: "")
                sessObj.put("start_time", session.startTime ?: "")
                sessObj.put("end_time", session.endTime ?: "")
                sessObj.put("room", session.room ?: "")
                sessObj.put("capacity", session.capacity)
                sessObj.put("speaker_name", session.speakerName ?: "")
                sessObj.put("speaker_role", session.speakerRole ?: "")
                sessObj.put("speaker_email", session.speakerEmail ?: "")
                sessObj.put("description", session.description ?: "")
                sessObj.put("skill_level", session.skillLevel ?: "Intermediate")
                sessionsArray.put(sessObj)
            }

            eventObj.put("event_sessions", sessionsArray)
            eventObj.put("sessions", sessionsArray) // Included for compatibility
            eventsArray.put(eventObj)
        }

        root.put("events", eventsArray)
        return root
    }

    /**
     * Executes HTTP POST request to Laravel backend.
     */
    fun syncEventsToCloud(
        serverUrl: String = DEFAULT_SERVER_URL,
        events: List<Event>,
        dbHelper: DatabaseHelper
    ): SyncResult {
        if (events.isEmpty()) {
            return SyncResult(true, 0, "No local events pending cloud upload.")
        }

        val jsonPayload = buildSyncPayload(events, dbHelper).toString()

        var conn: HttpURLConnection? = null
        try {
            val url = URL(serverUrl)
            conn = (url.openConnection() as HttpURLConnection).apply {
                requestMethod = "POST"
                connectTimeout = 10000
                readTimeout = 10000
                doOutput = true
                doInput = true
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Accept", "application/json")
            }

            OutputStreamWriter(conn.outputStream, "UTF-8").use { writer ->
                writer.write(jsonPayload)
                writer.flush()
            }

            val responseCode = conn.responseCode
            val inputStream = if (responseCode in 200..299) conn.inputStream else conn.errorStream
            val responseText = BufferedReader(InputStreamReader(inputStream, "UTF-8")).use { it.readText() }

            if (responseCode in 200..299) {
                val responseJson = JSONObject(responseText)
                val status = responseJson.optString("status", "success")
                val message = responseJson.optString("message", "Synced $events.size events to Laravel backend.")

                return if (status == "success") {
                    SyncResult(true, events.size, message)
                } else {
                    SyncResult(false, 0, "Laravel API Error: $message")
                }
            } else {
                return SyncResult(false, 0, "HTTP Error $responseCode: $responseText")
            }

        } catch (e: Exception) {
            return SyncResult(false, 0, "Network connection error: ${e.message}")
        } finally {
            conn?.disconnect()
        }
    }
}
