<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'name', 'category', 'date', 'start_time', 'end_time',
        'room', 'capacity', 'speaker_name', 'speaker_role', 'speaker_email',
        'description', 'skill_level'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
