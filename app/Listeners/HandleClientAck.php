<?php

namespace App\Listeners;

use Laravel\Reverb\Events\MessageReceived;
use Illuminate\Support\Facades\Log;
use App\Models\UserNotification;

class HandleClientAck
{
    public function handle(MessageReceived $event)
    {
        Log::info('Reverb message received', [
            'event_object' => get_object_vars($event)
        ]);
    
        // Decode the JSON string to array
        $message = json_decode($event->message, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to decode Reverb message', ['error' => json_last_error_msg()]);
            return;
        }

        // Now safely access the event name
        $eventName = $message['event'] ?? '';

        if (!str_starts_with($eventName, 'client-')) {
            Log::info('Ignoring non-client event', ['event' => $eventName]);
            return;
        }

        Log::info('Processing client event', ['event' => $eventName]);

        if ($eventName === 'client-ack') {
            $data = $message['data'] ?? [];
            $notificationId = $data['notification_id'] ?? null;
            $messageId = $data['message_id'] ?? null;
            $userId = $data['user_id'] ?? null;

            // Get user from connection (your WebsocketUser)
            Log::info('Client ACK received', [
                'user_id' => $userId,
                'notification_id' => $notificationId,
                'channel' => $message['channel'] ?? 'unknown',
                'sender_channel' => $message['sender_channel'] ?? 'unknown',
            ]);

            if ($notificationId && $userId) {
                $userNotif = UserNotification::where('user_id', $userId)
                    ->where('notification_id', $notificationId)
                    ->first();

                if ($userNotif && $userNotif->status === 'pending') {
                    $userNotif->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                    ]);

                    if ($message['token'] && env('QUARKUS_BASE_URL')) {
                        Http::withHeaders(['Authorization' => 'Bearer ' . $message['token']])
                            ->post(env('QUARKUS_BASE_URL') . '/api/admin/messages/ack', [
                                'messageId' => $messageId,
                                'recipientId' => $userId,
                                'newStatus' => 'DELIVERED',
                            ]);

                        Log::info('Notification marked as delivered via ACK', [
                            'notification_id' => $notificationId,
                            'user_id' => $userId,
                        ]);
                    }
                    broadcast(new MessageEvent([
                        'messageId' => $messageId,
                        'status' => 'DELIVERED',
                        'channel'  => $message['sender_channel'],
                    ], "{$message['sender_channel']}", 'NewMessage'))->toOthers();
                }
            }

        }
    }
}