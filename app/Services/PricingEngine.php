<?php

namespace App\Services;

use App\Exceptions\InvalidPricingConfigurationException;
use Carbon\Carbon;

class PricingEngine
{
    public const BASE_DAILY_RATES = [
        'BASIC' => '12.00',
        'PLUS' => '20.00',
        'PREMIUM' => '30.00',
    ];

    public const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => '1.00',
        'INTERNATIONAL' => '1.80',
    ];

    public const COVERAGE_MULTIPLIERS = [
        100000 => '1.00',
        300000 => '1.25',
        500000 => '1.50',
    ];

    /**
     * Calculate deterministic insurance premium using decimal-safe arithmetic.
     * Formula: base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
     *
     * @throws InvalidPricingConfigurationException
     */
    public function calculate(
        string $planCode,
        string|int $sumInsured,
        string $destination,
        string $dateOfBirth,
        string $tripStart,
        string $tripEnd
    ): array {
        $plan = strtoupper(trim($planCode));
        if (!array_key_exists($plan, self::BASE_DAILY_RATES)) {
            throw new InvalidPricingConfigurationException("Unsupported plan code: '{$planCode}'. Allowed: " . implode(', ', array_keys(self::BASE_DAILY_RATES)));
        }
        $baseDailyRate = self::BASE_DAILY_RATES[$plan];

        $sumInsuredInt = (int) $sumInsured;
        if (!array_key_exists($sumInsuredInt, self::COVERAGE_MULTIPLIERS)) {
            throw new InvalidPricingConfigurationException("Unsupported sum insured: '{$sumInsured}'. Allowed: " . implode(', ', array_keys(self::COVERAGE_MULTIPLIERS)));
        }
        $coverageMultiplier = self::COVERAGE_MULTIPLIERS[$sumInsuredInt];

        $dest = strtoupper(trim($destination));
        if (!array_key_exists($dest, self::DESTINATION_MULTIPLIERS)) {
            throw new InvalidPricingConfigurationException("Unsupported destination: '{$destination}'. Allowed: " . implode(', ', array_keys(self::DESTINATION_MULTIPLIERS)));
        }
        $destinationMultiplier = self::DESTINATION_MULTIPLIERS[$dest];

        $startDate = Carbon::parse($tripStart)->startOfDay();
        $endDate = Carbon::parse($tripEnd)->startOfDay();
        $dob = Carbon::parse($dateOfBirth)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new InvalidPricingConfigurationException("Trip end date ({$tripEnd}) cannot be earlier than trip start date ({$tripStart}).");
        }

        // Inclusive: trip_end - trip_start + 1; minimum 1
        $travelDays = (int) $startDate->diffInDays($endDate) + 1;
        if ($travelDays < 1) {
            $travelDays = 1;
        }

        // Age calculated on trip_start
        $ageAtTripStart = (int) $dob->diffInYears($startDate);
        if ($startDate->lt($dob)) {
            throw new InvalidPricingConfigurationException("Trip start date cannot be before date of birth.");
        }

        $ageMultiplier = $this->getAgeMultiplier($ageAtTripStart);

        // Decimal-safe calculation using bcmath with scale 4
        // premium = base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
        $step1 = bcmul($baseDailyRate, (string) $travelDays, 4);
        $step2 = bcmul($step1, $ageMultiplier, 4);
        $step3 = bcmul($step2, $destinationMultiplier, 4);
        $step4 = bcmul($step3, $coverageMultiplier, 4);

        // Round final premium to 2 decimal places
        $finalPremium = number_format((float) $step4, 2, '.', '');

        return [
            'final_premium' => $finalPremium,
            'travel_days' => $travelDays,
            'age_at_trip_start' => $ageAtTripStart,
            'breakdown' => [
                'base_daily_rate' => $baseDailyRate,
                'travel_days' => $travelDays,
                'age_at_trip_start' => $ageAtTripStart,
                'age_multiplier' => $ageMultiplier,
                'destination_multiplier' => $destinationMultiplier,
                'coverage_multiplier' => $coverageMultiplier,
                'calculation_formula' => "{$baseDailyRate} × {$travelDays} × {$ageMultiplier} × {$destinationMultiplier} × {$coverageMultiplier} = {$finalPremium}",
            ],
        ];
    }

    /**
     * Age multiplier rules:
     * 0–17: 1.10
     * 18–60: 1.00
     * 61–70: 1.50
     * 71+: 2.00
     */
    public function getAgeMultiplier(int $age): string
    {
        if ($age < 0) {
            return '1.00';
        }
        if ($age <= 17) {
            return '1.10';
        }
        if ($age <= 60) {
            return '1.00';
        }
        if ($age <= 70) {
            return '1.50';
        }
        return '2.00';
    }
}
