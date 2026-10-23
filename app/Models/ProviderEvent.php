<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'provider_reference',
        'event_type',
        'payload',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
