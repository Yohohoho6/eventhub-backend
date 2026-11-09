<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_code', 'name', 'date', 'location', 'organizer',
        'description', 'contact_email', 'contact_phone', 'created_at_local', 'photo_path',
    ];

    public function sessions()
    {
        return $this->hasMany(EventSession::class);
    }
}
