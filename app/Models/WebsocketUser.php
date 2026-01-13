<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class WebsocketUser extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $fillable = ['user_id', 'token', 'session_id', 'expires_at', 'type', 'name'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
