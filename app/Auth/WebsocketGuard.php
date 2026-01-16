<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WebsocketUser;
use Illuminate\Support\Facades\Log;

class WebsocketGuard implements Guard
{
    protected $provider;
    protected $request;
    protected $user;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request  = $request;

        Log::info('WebsocketGuard initialized');
    }

    public function check()   { return !is_null($this->user()); }
    public function guest()   { return !$this->check(); }
    public function hasUser() { return !is_null($this->user); }
    public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user = null) { $this->user = $user; }
    public function id()      { return $this->user()?->getAuthIdentifier(); }

    public function user()
    {
        Log::info('WebsocketGuard: Attempting to retrieve user');
        if ($this->user !== null) {
            Log::info('WebsocketGuard: Returning cached user', ['user' => $this->user]);
            return $this->user;
        }

        $accessToken = $this->request->header('access-token');
        $sessionId = $this->request->header('session-id');
        $actor = $this->request->header('x-actor-type', 'user');

        Log::info('WebsocketGuard: Retrieved headers', [
            'access_token' => $accessToken ? 'present' : 'missing',
            'session_id' => $sessionId ? 'present' : 'missing',
            'actor' => $actor,
        ]);

        if (!$accessToken || !$sessionId) {
            return null;
        }

        // Fast path: cached token
        $wsUser = WebsocketUser::where('token', $accessToken)
            ->where('session_id', $sessionId)
            ->where('expires_at', '>', now())
            ->first();
        Log::info('WebsocketGuard: Cached user lookup', ['user' => $wsUser, 'actor' => $actor]);
        
        if ($wsUser) {
            // Verify actor type matches cached type
            if ($wsUser->type !== $actor) {
                Log::warning('WebsocketGuard: Actor type mismatch in cache', [
                    'cached_type' => $wsUser->type,
                    'requested_actor' => $actor,
                ]);
                return null;
            }
            
            $this->user = $wsUser;
            return $wsUser;
        }

        // Slow path: verify with your external auth server
        $response = Http::withHeaders([
            'access-token' => $accessToken,
            'session-id' => $sessionId,
            'x-actor-type' => $actor,
        ])->withOptions([
            'verify' => app()->environment('production') ? true : false,
        ])->get(env('AUTH_SERVER_URL') ?? 'https://www.update.ayudahub.com/ghana/api/verify-token');
        Log::info('WebsocketGuard: External auth response', ['status' => $response->status(), 'body' => $response->body()]);
        if (!$response->successful() || empty($response->json('user_id'))) {
            return null;
        }

        $data = $response->json();
        $returnedType = $data['type'] ?? $actor;
        
        // Verify that the returned type matches the requested actor type
        if ($returnedType !== $actor) {
            Log::warning('WebsocketGuard: Actor type mismatch', [
                'expected' => $actor,
                'received' => $returnedType,
            ]);
            return null;
        }
        
        Log::info('WebsocketGuard: External auth succeeded', [
            'data' => $data,
            'actor' => $actor,
        ]);

        // Cache it
        $wsUser = WebsocketUser::updateOrCreate([
            'user_id' => $data['user_id'],
            'session_id' => $sessionId,
        ], [
            'token' => $accessToken,
            'expires_at' => now()->addHour(),
            'type' => $returnedType,
            'name' => $data['name'] ?? null,
        ]);

        $this->user = $wsUser;
        return $wsUser;
    }

    public function validate(array $credentials = []) { return false; } // not used for token auth
}