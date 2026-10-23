<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    use HasFactory;

    // Supported Statuses
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    public const STATUS_ISSUED = 'ISSUED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_NEEDS_RECONCILIATION = 'NEEDS_RECONCILIATION';

    public const ALL_STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_VALIDATED,
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING_CONFIRMATION,
        self::STATUS_ISSUED,
        self::STATUS_FAILED,
        self::STATUS_NEEDS_RECONCILIATION,
    ];

    protected $fillable = [
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'travel_days',
        'calculated_premium',
        'pricing_breakdown',
        'status',
        'policy_number',
        'provider_reference',
        'attempt_count',
        'last_response',
        'last_error',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'pricing_breakdown' => 'array',
        'last_response' => 'array',
        'calculated_premium' => 'decimal:2',
        'sum_insured' => 'integer',
        'travel_days' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class)->orderBy('attempt_number');
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('created_at');
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isTerminal(): bool
    {
        return $this->status === self::STATUS_ISSUED || $this->status === self::STATUS_FAILED;
    }
}
