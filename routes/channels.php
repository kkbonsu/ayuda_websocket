<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\WebsocketUser;
use App\Broadcasting\MessageChannel;

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });

Broadcast::channel('user.{id}', MessageChannel::class, ['guards' => ['websocket']]);
Broadcast::channel('group.{roomId}', MessageChannel::class, ['guards' => ['websocket']]);
Broadcast::channel('{channel}', MessageChannel::class, ['guards' => ['websocket']]);