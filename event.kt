package com.example.eventhub.model

import java.io.Serializable

class Event(
    var id: Long = 0,
    var eventCode: String? = null,
    var name: String? = null,
    var date: String? = null,
    var location: String? = null,
    var organizer: String? = null,
    var description: String? = null,
    var contactEmail: String? = null,
    var contactPhone: String? = null,
    var synced: Int = 0,
    var createdAt: String? = null,
    var photoPath: String? = null,
    var sessionCount: Int = 0
) : Serializable {
    constructor(
        id: Long, eventCode: String?, name: String?, date: String?, location: String?,
        organizer: String?, description: String?, contactEmail: String?, contactPhone: String?,
        isSynced: Int, createdAt: String?
    ) : this(id, eventCode, name, date, location, organizer, description, contactEmail,
        contactPhone, isSynced, createdAt, null, 0)

    constructor(
        id: Long, eventCode: String?, name: String?, date: String?, location: String?,
        organizer: String?, description: String?, contactEmail: String?, contactPhone: String?,
        isSynced: Int, createdAt: String?, photoPath: String?
    ) : this(id, eventCode, name, date, location, organizer, description, contactEmail,
        contactPhone, isSynced, createdAt, photoPath, 0)

    fun getIsSynced(): Int = synced
    fun setIsSynced(value: Int) { synced = value }
}
