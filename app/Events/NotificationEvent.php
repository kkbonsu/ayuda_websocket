<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Notification $notification;

    public function __construct($notification)
    {
        $this->notification = $notification;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        Log::info('Broadcasting on channel', ['channel' => $this->notification->channel]);
        // return [new Channel($this->notification->channel)];
        return [
            new PresenceChannel($this->notification->channel),
        ];
    }

    public function broadcastWith()
    {
        $data = $this->notification->data ?? [];

        return [
            'id' => $this->notification->id,
            'id' => $this->notification->id,
            'title' => $this->notification->title ?? $this->getDefaultTitle(),
            'message' => $data['message'] ?? null,
            'event_type' => $this->notification->event,
            'notification_type' => $data['notification_type'] ?? null,
            'channel' => $this->notification->channel,
            'timestamp' => $this->notification->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'data' => $data,
            'metaData' => $data['metaData'] ?? null,
            // Include common event details from metadata
            'event_details' => $this->extractEventDetails($data),
        ];
    }

    public function broadcastAs(){
        return $this->notification->event;
    }

    /**
     * Get default title based on event type if title is not provided
     */
    private function getDefaultTitle(): string
    {
        $eventType = $this->notification->event;
        
        // Map common event types to default titles
        $defaultTitles = [
            'notification' => 'New Notification',
            'SERVICE_REQUEST' => 'Service Request Update',
            'SERVICE_REQUEST_FLEX' => 'Flex Service Request',
            'OFFER_MADE' => 'New Offer',
            'OFFER_ACCEPTED' => 'Offer Accepted',
            'OFFER_REJECTED' => 'Offer Rejected',
            'INVOICE' => 'Invoice Update',
            'COMPLAINT' => 'Complaint Update',
            'WITHDRAWAL_REQUEST' => 'Withdrawal Request',
        ];

        return $defaultTitles[$eventType] ?? 'Notification';
    }

    /**
     * Extract structured event details from metadata
     */
    private function extractEventDetails(array $data): array
    {
        $details = [];
        $metaData = $data['metaData'] ?? [];

        // Extract common event details
        if (isset($metaData['service_request_id'])) {
            $details['service_request_id'] = $metaData['service_request_id'];
        }
        
        if (isset($metaData['service_request_number'])) {
            $details['service_request_number'] = $metaData['service_request_number'];
        }
        
        if (isset($metaData['offer_id'])) {
            $details['offer_id'] = $metaData['offer_id'];
        }
        
        if (isset($metaData['invoice_id'])) {
            $details['invoice_id'] = $metaData['invoice_id'];
        }
        
        if (isset($metaData['complaint_id'])) {
            $details['complaint_id'] = $metaData['complaint_id'];
        }
        
        if (isset($metaData['user_id'])) {
            $details['user_id'] = $metaData['user_id'];
        }
        
        if (isset($metaData['worker_id'])) {
            $details['worker_id'] = $metaData['worker_id'];
        }
        
        if (isset($metaData['status'])) {
            $details['status'] = $metaData['status'];
        }
        
        if (isset($metaData['amount'])) {
            $details['amount'] = $metaData['amount'];
        }

        return $details;
    }
}
