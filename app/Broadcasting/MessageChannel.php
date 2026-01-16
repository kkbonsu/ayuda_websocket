<?php

// app/Broadcasting/MessageChannel.php

namespace App\Broadcasting;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\WebsocketUser;
use App\Models\ChatMessage;
use App\Models\UserNotification;
use App\Events\MessageEvent;
use Illuminate\Support\Facades\Artisan;

class MessageChannel
{
    /**
     * Authorize the user to join the presence channel.
     */
    public function join($user, $channel)
    {
        $accessToken = request()->header('access-token');
        $sessionId = request()->header('session-id');
        $actor = request()->header('x-actor-type', 'user');
        $socketId  = request()->header('X-Socket-ID');

        Log::debug('MessageChannel join attempt', [
            'channel'   => $channel,
            'socket_id' => $socketId,
            'access_token'     => $accessToken ? 'present' : 'missing',
            'session_id' => $sessionId ? 'present' : 'missing',
            'actor' => $actor,
        ]);

        if (!$accessToken || !$sessionId) {
            return false;
        }

        // Validate channel access based on actor type
        if (preg_match('/^user\.(.+)$/', $channel, $matches)) {
            // User channel - only allow if actor is 'user'
            if ($actor !== 'user') {
                Log::warning('Worker attempted to join user channel', [
                    'channel' => $channel,
                    'actor' => $actor,
                ]);
                return false;
            }
        } elseif (preg_match('/^worker\.(.+)$/', $channel, $matches)) {
            // Worker channel - only allow if actor is 'worker'
            if ($actor !== 'worker') {
                Log::warning('User attempted to join worker channel', [
                    'channel' => $channel,
                    'actor' => $actor,
                ]);
                return false;
            }
        }

        Log::info('Authorizing WebSocket channel join', [
            'channel' => $channel,
            'actor' => $actor,
        ]);
        
        // Fast path: cached token
        $cached = WebsocketUser::where('token', $accessToken)
            ->where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached) {
            Log::info('WebSocket user found in cache', [
                'user_id' => $cached->user_id,
                'channel' => $channel,
                'type' => $cached->type,
            ]);
            
            // Verify actor type matches cached type
            if ($cached->type !== $actor) {
                Log::warning('Actor type mismatch between cached and request', [
                    'cached_type' => $cached->type,
                    'requested_actor' => $actor,
                    'channel' => $channel,
                ]);
                return false;
            }
            
            return $this->presenceData($cached->user_id, [
                'name' => $cached->name,
                'type' => $cached->type,
            ]);
        }

        // Slow path: verify with external auth server
        $response = Http::withHeaders([
            'access-token' => $accessToken,
            'session-id' => $sessionId,
            'x-actor-type' => $actor,
        ])->get(env('AUTH_SERVER_URL'));

        if (!$response->successful() || empty($response->json('user_id'))) {
            Log::warning('External auth failed for WebSocket', [
                'status' => $response->status(),
                'channel' => $channel,
            ]);
            return false;
        }

        $data = $response->json();
        $returnedType = $data['type'] ?? $actor;
        
        // Verify that the returned type matches the requested actor type
        if ($returnedType !== $actor) {
            Log::warning('Actor type mismatch between backend and request', [
                'expected' => $actor,
                'received' => $returnedType,
                'channel' => $channel,
            ]);
            return false;
        }
        
        Log::info('WebSocket user verified via external auth', [
            'user_id' => $data['user_id'],
            'channel' => $channel,
            'type' => $returnedType,
        ]);
        
        // Cache for 1 hour
        try {
            WebsocketUser::updateOrCreate(
                ['user_id' => $data['user_id'], 'session_id' => $sessionId],
                [
                    'token' => $accessToken,
                    'expires_at' => now()->addHour(),
                    'type' => $returnedType,
                    'name' => $data['name'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to cache websocket token', ['error' => $e->getMessage()]);
        }

        return $this->presenceData($data['user_id'], $data);
    }

    /**
     * Return the data sent to clients in "here", "joining", "leaving" events.
     */
    private function presenceData(String $userId, array $extra = []): array
    {
        $user = \App\Models\WebsocketUser::where('user_id', $userId)->first();

        return [
            'user_id'     => $userId,
            'name'   => $extra['name'] ?? $user?->name ?? $extra['email'] ?? 'User',
            'type'   => $extra['type'] ?? $user?->type ?? 'user',
            // 'avatar' => $user?->avatar ?? $extra['avatar'] ?? null,
            // Add anything else you want visible in the member list
        ];
    }

    /**
     * Called when a user successfully subscribes.
     * Here we register client event listeners (typing, messages).
     */
    public function subscribed($event, $channel)
    {
        Log::info('User joined chat room', [
            'channel'  => $channel,
            'user_id'  => $event->user['id'],
            'socket_id'=> request()->header('X-Socket-ID'),
        ]);

        // Typing Indicator
        $event->listenForWhisper('client-typing', function ($payload) use ($channel, $event) {
            echo "[INFO] User client-typing - ID: $channel\n";
            broadcast(new MessageEvent([
                'user_id' => $event->user['id'],
                'name'    => $event->user['name'],
                'typing'  => (bool) ($payload['typing'] ?? false),
            ], "presence-chat.{$channel}", 'UserTyping'))->toOthers();
        });

        // New Message from Flutter/Web
        $event->listenForWhisper('client-message', function ($payload) use ($channel, $event) {
            $userId = $event->user['id'];
            $text   = trim($payload['text'] ?? $payload['message'] ?? '');

            if (!$text) {
                return;
            }

            // Save to database
            try {
                echo "[INFO] User client-message - ID: $channel\n";
                ChatMessage::create([
                    'channel' => $channel,
                    'user_id' => $userId,
                    'message' => $text,
                    'sent_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Could not save chat message', ['error' => $e->getMessage()]);
            }

            // Broadcast to others
            broadcast(new MessageEvent([
                'user_id'  => $userId,
                'name'     => $event->user['name'],
                'avatar'   => $event->user['avatar'] ?? null,
                'message'  => $text,
                'sent_at'  => now()->toDateTimeString(),
                'channel'  => $channel,
            ], "{$channel}", 'NewMessage'))->toOthers();
        });
    }
}