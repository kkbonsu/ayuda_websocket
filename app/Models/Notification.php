<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['channel', 'event', 'title', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}
