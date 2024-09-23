<?php

namespace App\Models;

use Laravel\Passport\Token;

class AccessToken extends Token
{
    protected $casts = [
        'revoked' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'firebase_uid'); // Modifier ici
    }
}
