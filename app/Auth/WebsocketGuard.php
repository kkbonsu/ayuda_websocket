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

        $token = $this->request->bearerToken();
        Log::info('WebsocketGuard: Retrieved token', ['token' => $token]);
        if (!$token) {
            return null;
        }

        // Fast path: cached token
        $wsUser = WebsocketUser::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
        Log::info('WebsocketGuard: Cached user lookup', ['user' => $wsUser]);
        if ($wsUser) {
            $this->user = $wsUser;
            return $wsUser;
        }

        // Slow path: verify with your external auth server
        $response = Http::withToken($token)->withOptions([
            'verify' => app()->environment('production') ? true : false,
        ])->get(env('AUTH_SERVER_URL') ?? 'http://127.0.0.1:8080/api/verify-token');
        Log::info('WebsocketGuard: External auth response', ['status' => $response->status(), 'body' => $response->body()]);
        if (!$response->successful() || empty($response->json('user_id'))) {
            return null;
        }

        $data = $response->json();
        Log::info('WebsocketGuard: External auth succeeded', ['data' => $data]);

        // Cache it
        $wsUser = WebsocketUser::updateOrCreate(
            ['user_id' => $data['user_id']],
            ['token' => $token, 'expires_at' => now()->addHour()]
        );

        $this->user = $wsUser;
        return $wsUser;
    }

    public function validate(array $credentials = []) { return false; } // not used for token auth
}