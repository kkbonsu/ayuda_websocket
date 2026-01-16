# WebSocket Implementation: User & Worker Support

## Overview

The WebSocket server now fully supports both **users** and **workers** with proper channel isolation, type validation, and security. This document explains the implementation for both backend engineers and frontend developers.

---

## For Senior Engineers

### Architecture Changes

#### 1. Channel Routing (`routes/channels.php`)

We've added dedicated channel routes for both user types:

```php
// User-specific channels - only users can join
Broadcast::channel('user.{id}', MessageChannel::class, ['guards' => ['websocket']]);

// Worker-specific channels - only workers can join
Broadcast::channel('worker.{id}', MessageChannel::class, ['guards' => ['websocket']]);

// Group/room channels (for both users and workers)
Broadcast::channel('group.{roomId}', MessageChannel::class, ['guards' => ['websocket']]);

// Generic channel (fallback for custom channels like notifications, ghana-flex-*, etc.)
Broadcast::channel('{channel}', MessageChannel::class, ['guards' => ['websocket']]);
```

**Key Points:**
- `user.{id}` channels are isolated to users only
- `worker.{id}` channels are isolated to workers only
- Group and generic channels allow both types
- All channels use the `websocket` guard for authentication

#### 2. Channel Authorization (`app/Broadcasting/MessageChannel.php`)

**Channel Type Validation:**
```php
// Validate channel access based on actor type
if (preg_match('/^user\.(.+)$/', $channel, $matches)) {
    // User channel - only allow if actor is 'user'
    if ($actor !== 'user') {
        return false; // Worker attempting to join user channel
    }
} elseif (preg_match('/^worker\.(.+)$/', $channel, $matches)) {
    // Worker channel - only allow if actor is 'worker'
    if ($actor !== 'worker') {
        return false; // User attempting to join worker channel
    }
}
```

**Actor Type Consistency Checks:**
- Validates cached token type matches requested actor type
- Validates backend response type matches requested actor type
- Prevents type spoofing and unauthorized access

#### 3. Authentication Guard (`app/Auth/WebsocketGuard.php`)

**Enhanced Security:**
- Validates actor type in cached tokens
- Validates actor type from backend verification
- Rejects mismatched actor types at guard level

**Flow:**
1. Check cache for valid token
2. If cached, verify actor type matches
3. If not cached, verify with backend (`/api/verify-token`)
4. Cache result with actor type
5. Return authenticated user/worker

#### 4. Broadcast Message Route (`routes/api.php`)

**Enhanced to Support Both Types:**
```php
// Supports both userIds and workerIds
$userIds = $request->input('userIds'); 
$workerIds = $request->input('workerIds'); 

// Auto-detect channel type
if (preg_match('/^user\.(\w+)$/', $channel, $matches)) {
    $userIdArray[] = $matches[1];
}
if (preg_match('/^worker\.(\w+)$/', $channel, $matches)) {
    $userIdArray[] = $matches[1];
}
```

**Key Features:**
- Automatically extracts user/worker ID from channel name
- Stores notifications for both types in `UserNotification` table
- Uses unified `user_id` field (works for both users and workers)

### Security Features

1. **Channel Isolation**: Users cannot join worker channels and vice versa
2. **Type Validation**: Actor type is validated at multiple levels
3. **Backend Verification**: All authentications verified with backend API
4. **Token Caching**: Reduces backend load with 1-hour token cache

### Backend Integration

The websocket server integrates with the backend's `/api/verify-token` endpoint:

**Request Headers:**
- `access-token`: JWT/API token
- `session-id`: Session identifier
- `x-actor-type`: Either `user` or `worker`

**Response:**
```json
{
    "user_id": "uuid-string",
    "name": "User/Worker Name",
    "email": "email@example.com",
    "type": "user" | "worker"
}
```

### Backward Compatibility

- Existing `user.{id}` channels continue to work
- Backend can still send worker notifications to `user.{workerId}` (current behavior)
- Backend can be updated to use `worker.{workerId}` channels when ready

---

## For Flutter Developers

### Connection Setup

#### 1. Required Headers

When connecting to the WebSocket server, you **must** include these headers:

```dart
final headers = {
  'access-token': userAccessToken,      // From your auth system
  'session-id': sessionId,              // From your auth system
  'x-actor-type': 'user',               // or 'worker'
};
```

**Important:** The `x-actor-type` header determines what channels you can join:
- `'user'` → Can join `user.{id}` channels
- `'worker'` → Can join `worker.{id}` channels

#### 2. Channel Naming Convention

**For Users:**
```dart
// User-specific channel
final channel = 'user.${userId}';  // e.g., 'user.123e4567-e89b-12d3-a456-426614174000'
```

**For Workers:**
```dart
// Worker-specific channel
final channel = 'worker.${workerId}';  // e.g., 'worker.123e4567-e89b-12d3-a456-426614174000'
```

**For Group Channels:**
```dart
// Both users and workers can join
final channel = 'group.${roomId}';  // e.g., 'group.chat-room-123'
```

### Flutter Implementation Example

#### Using Laravel Echo (Recommended)

```dart
import 'package:laravel_echo/laravel_echo.dart';
import 'package:socket_io_client/socket_io_client.dart' as IO;

class WebSocketService {
  late Echo echo;
  final String userId;
  final String accessToken;
  final String sessionId;
  final String actorType; // 'user' or 'worker'

  WebSocketService({
    required this.userId,
    required this.accessToken,
    required this.sessionId,
    required this.actorType,
  });

  void connect() {
    // Determine channel prefix based on actor type
    final channelPrefix = actorType == 'worker' ? 'worker' : 'user';
    final channel = '$channelPrefix.$userId';

    echo = Echo({
      'broadcaster': 'reverb',
      'key': 'your-reverb-key',
      'wsHost': 'your-websocket-server.com',
      'wsPort': 8080,
      'wssPort': 443,
      'forceTLS': true,
      'enabledTransports': ['ws', 'wss'],
      'auth': {
        'headers': {
          'access-token': accessToken,
          'session-id': sessionId,
          'x-actor-type': actorType,
        },
      },
    });

    // Subscribe to your channel
    echo.private(channel)
        .listen('.notification', (e) {
          print('Notification received: ${e}');
          // Handle notification
        });
  }

  void disconnect() {
    echo.disconnect();
  }
}
```

#### Using Socket.IO Directly

```dart
import 'package:socket_io_client/socket_io_client.dart' as IO;

class WebSocketService {
  late IO.Socket socket;
  final String userId;
  final String accessToken;
  final String sessionId;
  final String actorType;

  WebSocketService({
    required this.userId,
    required this.accessToken,
    required this.sessionId,
    required this.actorType,
  });

  void connect() {
    final channelPrefix = actorType == 'worker' ? 'worker' : 'user';
    final channel = '$channelPrefix.$userId';

    socket = IO.io(
      'wss://your-websocket-server.com',
      IO.OptionBuilder()
          .setTransports(['websocket'])
          .setExtraHeaders({
            'access-token': accessToken,
            'session-id': sessionId,
            'x-actor-type': actorType,
          })
          .build(),
    );

    socket.onConnect((_) {
      print('Connected to WebSocket');
      
      // Subscribe to channel
      socket.emit('subscribe', {
        'channel': channel,
        'auth': {
          'headers': {
            'access-token': accessToken,
            'session-id': sessionId,
            'x-actor-type': actorType,
          },
        },
      });
    });

    // Listen for notifications
    socket.on('notification', (data) {
      print('Notification: $data');
      // Handle notification
    });

    socket.onDisconnect((_) {
      print('Disconnected from WebSocket');
    });
  }

  void disconnect() {
    socket.disconnect();
  }
}
```

### Error Handling

#### Channel Access Denied

If you try to join a channel with the wrong actor type, you'll receive an error:

```dart
socket.onError((error) {
  if (error.toString().contains('403') || 
      error.toString().contains('Forbidden')) {
    print('Channel access denied. Check your x-actor-type header.');
  }
});
```

**Common Issues:**
1. **User trying to join `worker.{id}` channel** → Access denied
2. **Worker trying to join `user.{id}` channel** → Access denied
3. **Missing `x-actor-type` header** → Defaults to 'user', may cause issues
4. **Mismatched actor type** → Access denied

### Listening to Events

#### Notification Events

```dart
// Listen for notifications
echo.private('user.$userId')
    .listen('.notification', (data) {
      final notification = NotificationModel.fromJson(data);
      // Update UI, show notification, etc.
    });

// Or for workers
echo.private('worker.$workerId')
    .listen('.notification', (data) {
      final notification = NotificationModel.fromJson(data);
      // Update UI, show notification, etc.
    });
```

#### Chat Messages (if using group channels)

```dart
echo.private('group.$roomId')
    .listen('.NewMessage', (data) {
      final message = ChatMessage.fromJson(data);
      // Add message to chat UI
    })
    .listen('.UserTyping', (data) {
      // Show typing indicator
    });
```

### Testing Checklist

1. ✅ **User Connection**
   - Connect with `x-actor-type: user`
   - Join `user.{userId}` channel
   - Receive notifications

2. ✅ **Worker Connection**
   - Connect with `x-actor-type: worker`
   - Join `worker.{workerId}` channel
   - Receive notifications

3. ✅ **Access Control**
   - User tries to join `worker.{id}` → Should fail
   - Worker tries to join `user.{id}` → Should fail

4. ✅ **Group Channels**
   - Both user and worker can join `group.{roomId}`
   - Both can send/receive messages

5. ✅ **Reconnection**
   - Test reconnection with same credentials
   - Verify channel subscription persists

### Common Patterns

#### User App
```dart
class UserWebSocketService extends WebSocketService {
  UserWebSocketService({
    required String userId,
    required String accessToken,
    required String sessionId,
  }) : super(
          userId: userId,
          accessToken: accessToken,
          sessionId: sessionId,
          actorType: 'user', // Always 'user' for user app
        );
}
```

#### Worker App
```dart
class WorkerWebSocketService extends WebSocketService {
  WorkerWebSocketService({
    required String workerId,
    required String accessToken,
    required String sessionId,
  }) : super(
          userId: workerId, // Note: still uses userId parameter
          accessToken: accessToken,
          sessionId: sessionId,
          actorType: 'worker', // Always 'worker' for worker app
        );
}
```

---

## Channel Types Reference

| Channel Pattern | Allowed Actor Types | Use Case |
|----------------|---------------------|----------|
| `user.{id}` | `user` only | User-specific notifications |
| `worker.{id}` | `worker` only | Worker-specific notifications |
| `group.{roomId}` | `user`, `worker` | Group chats, shared rooms |
| `{custom}` | `user`, `worker` | Custom channels (e.g., `ghana-flex-*`) |

---

## Troubleshooting

### Issue: "Access Denied" when joining channel

**Possible Causes:**
1. Wrong `x-actor-type` header value
2. Channel name doesn't match actor type
3. Token expired or invalid
4. Session ID mismatch

**Solution:**
- Verify `x-actor-type` matches channel type
- Check token and session ID are valid
- Ensure channel name format is correct

### Issue: Not receiving notifications

**Possible Causes:**
1. Not subscribed to correct channel
2. Backend sending to wrong channel format
3. Connection dropped

**Solution:**
- Verify channel subscription
- Check backend is using correct channel format
- Implement reconnection logic

### Issue: Multiple connections

**Possible Causes:**
1. Not disconnecting previous connection
2. Multiple instances of service

**Solution:**
- Always disconnect before creating new connection
- Use singleton pattern for WebSocket service

---

## API Endpoints

### Broadcast Message (Backend → WebSocket Server)

```http
POST /broadcast-message
Headers:
  X-Reverb-App-ID: {app_id}
  X-Reverb-App-Secret: {app_secret}
  
Body:
{
  "channel": "user.{userId}" | "worker.{workerId}",
  "event": "notification",
  "data": { ... },
  "userIds": ["userId1", "userId2"]  // Optional
}
```

### Pending Notifications (Client → WebSocket Server)

```http
GET /pending-notifications
Headers:
  access-token: {token}
  session-id: {sessionId}
  x-actor-type: user | worker
```

---

## Questions?

For technical questions, contact the backend team. For implementation help, refer to the Flutter examples above.
