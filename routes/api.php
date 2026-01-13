<?php

use App\Events\MessageEvent;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Http;
use App\Models\WebsocketUser;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Events\NotificationEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Broadcasting\BroadcastSubscribedEvent;

// routes/api.php
use App\Broadcasting\MessageChannel;
use App\Models\User;

Route::post('/broadcast-message', function (Request $request) {
    $appId = $request->header('X-Reverb-App-ID') ?: $request->input('app_id');
    $appSecret = $request->header('X-Reverb-App-Secret') ?: $request->input('app_secret');

    Log::debug('Broadcast request received', [
        'app_id' => $appId,
        'app_secret' => $appSecret,
        'ip' => $request->ip(),
    ]);

    // Validate app credentials
    Log::info('Validating app credentials', [
        'app_id' => $appId,
        'app_secret' => $appSecret,
        'env_app_id' => env('REVERB_APP_ID'),
        'env_app_secret' => env('REVERB_APP_SECRET'),
    ]);
    if ($appId != env('REVERB_APP_ID') || $appSecret != env('REVERB_APP_SECRET')) {

        Log::warning('Invalid app credentials for broadcast', [
            'app_id' => $appId,
            'ip' => $request->ip(),
        ]);
        return response()->json(['error' => 'Unauthorized application'], 401);
    }

    $data = $request->input('data');
    $channel = $request->input('channel');
    $event = $request->input('event');

    if (!$data || !$channel || !$event) {
        Log::warning('Invalid broadcast parameters', [
            'data' => $data,
            'channel' => $channel,
            'event' => $event,
            'ip' => $request->ip(),
        ]);
        return response()->json(['error' => 'Data, channel, and event required'], 400);
    }

    $notification=Notification::create([
        'channel' => $channel,
        'event' => $event,
        'data' => $data,
    ]);

    // Handle per-user storage with Quarkus UUIDs
    $userIds = $request->input('userIds'); // comma-separated or array
    $userIdArray = [];
    if ($userIds) {
        $userIdArray = is_string($userIds) ? array_map('trim', explode(',', $userIds)) : (array) $userIds;
    }

    if (preg_match('/^user\.(\w+)$/', $channel, $matches)) {
        Log::info('Channel is user-specific', ['channel' => $channel, 'user_id' => $matches[1]]);   
        $userIdArray[] = $matches[1]; // Add single user if channel is user-specific
    }

    $userIdArray = array_unique($userIdArray); // Dedup

    foreach ($userIdArray as $userId) {
        if ($userId) {
            UserNotification::create([
                'user_id' => $userId, // string UUID
                'notification_id' => $notification->id,
            ]);
        }
    }

    broadcast(new NotificationEvent($notification));
    Log::info('Broadcast triggered', [
        'channel' => $channel,
        'event' => $event,
        'app_id' => $appId,
    ]);
    echo "[INFO] Broadcast triggered on notification: $notification, event: $event\n";

    return response()->json(['message' => 'Data broadcasted!']);
});

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::middleware('auth:websocket')->get('/pending-notifications', function (Request $request) {
    Log::info('Fetching pending notifications for user', [
        'user_id' => $request->user()->user_id,
    ]);
    $userId = $request->user()->user_id;  // From WebsocketUser
    $pending = UserNotification::where('user_id', $userId)
        ->where('status', 'pending')
        ->with('notification')
        ->get();

    // Broadcast them now (client will ack)
    foreach ($pending as $userNotif) {
        Log::info('Re-broadcasting pending notification', [
            'notification_id' => $userNotif->notification->id,
            'user_id' => $userId,
        ]);
        broadcast(new NotificationEvent($userNotif->notification));
    }
    Log::info('Total pending notifications re-broadcasted', [
        'user_id' => $userId,
        'count' => $pending->count(),
    ]);
    return response()->json(['message' => 'Data re-broadcasted!']);
});