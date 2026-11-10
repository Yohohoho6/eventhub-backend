<?php

use App\Http\Controllers\Api\EventSyncController;
use Illuminate\Support\Facades\Route;

Route::post('/events/sync', [EventSyncController::class, 'syncEvents']);
Route::get('/events', [EventSyncController::class, 'getEvents']);
Route::get('/events/{event}', [EventSyncController::class, 'getEvent']);
