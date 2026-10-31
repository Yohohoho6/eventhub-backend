<?php

use App\Http\Controllers\Api\EventSyncController;
use Illuminate\Support\Facades\Route;

Route::get('/events', [EventSyncController::class, 'getEvents']);