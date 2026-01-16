# WebSocket Quick Reference: User & Worker Support

## 🎯 Quick Summary

The WebSocket server now supports both **users** and **workers** with proper channel isolation. Users can only join `user.{id}` channels, workers can only join `worker.{id}` channels.

---

## 🔑 Key Headers (Required for Connection)

```dart
{
  'access-token': userAccessToken,    // From auth system
  'session-id': sessionId,            // From auth system  
  'x-actor-type': 'user' | 'worker'  // CRITICAL: Determines channel access
}
```

---

## 📡 Channel Naming

| Actor Type | Channel Format | Example |
|------------|---------------|---------|
| `user` | `user.{userId}` | `user.123e4567-e89b-12d3-a456-426614174000` |
| `worker` | `worker.{workerId}` | `worker.123e4567-e89b-12d3-a456-426614174000` |
| Both | `group.{roomId}` | `group.chat-room-123` |

---

## ✅ What Works

- ✅ Users connect with `x-actor-type: user` → Join `user.{id}` channels
- ✅ Workers connect with `x-actor-type: worker` → Join `worker.{id}` channels  
- ✅ Both can join `group.{roomId}` channels
- ✅ Backend can send notifications to both channel types
- ✅ Automatic channel type detection in broadcast route

---

## ❌ What Doesn't Work (Security)

- ❌ User trying to join `worker.{id}` → **Access Denied**
- ❌ Worker trying to join `user.{id}` → **Access Denied**
- ❌ Wrong `x-actor-type` header → **Access Denied**

---

## 🚀 Flutter Quick Start

```dart
// For Users
final channel = 'user.$userId';
final headers = {
  'access-token': accessToken,
  'session-id': sessionId,
  'x-actor-type': 'user',  // Must be 'user'
};

// For Workers  
final channel = 'worker.$workerId';
final headers = {
  'access-token': accessToken,
  'session-id': sessionId,
  'x-actor-type': 'worker',  // Must be 'worker'
};
```

---

## 🔒 Security Features

1. **Channel Isolation** - Users/workers can only join their respective channels
2. **Type Validation** - Actor type validated at multiple levels
3. **Backend Verification** - All auth verified with backend API
4. **Token Caching** - 1-hour cache reduces backend load

---

## 📋 Testing Checklist

- [ ] User connects with `x-actor-type: user` → Joins `user.{id}` ✅
- [ ] Worker connects with `x-actor-type: worker` → Joins `worker.{id}` ✅
- [ ] User tries `worker.{id}` → Access denied ❌
- [ ] Worker tries `user.{id}` → Access denied ❌
- [ ] Both join `group.{roomId}` → Success ✅
- [ ] Notifications received on correct channels ✅

---

## 🐛 Common Issues

| Issue | Cause | Solution |
|------|-------|----------|
| Access Denied | Wrong `x-actor-type` | Match header to channel type |
| No Notifications | Wrong channel | Use `user.{id}` or `worker.{id}` |
| Connection Failed | Missing headers | Include all 3 required headers |

---

## 📚 Full Documentation

See `WEBSOCKET_USER_WORKER_IMPLEMENTATION.md` for complete details.
