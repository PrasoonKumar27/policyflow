<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockInsurerRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_reference',
        'client_request_id',
        'agent_id',
        'policy_number',
        'status',
        'scenario',
        'premium',
        'payload',
    ];

    protected $casts = [
        'premium' => 'decimal:2',
        'payload' => 'array',
    ];
}
