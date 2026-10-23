<?php

namespace Tests\Unit;

use App\Exceptions\InvalidPricingConfigurationException;
use App\Services\PricingEngine;
use PHPUnit\Framework\TestCase;

class PricingEngineTest extends TestCase
{
    protected PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PricingEngine();
    }

    /**
     * Test the exact assessment prompt example:
     * - PLUS plan (₹20/day)
     * - 5 days inclusive (2026-11-10 to 2026-11-14)
     * - Age 28 on trip start (1998-04-10) -> multiplier 1.00
     * - INTERNATIONAL destination -> multiplier 1.80
     * - ₹300,000 coverage -> multiplier 1.25
     * Expected: 20 × 5 × 1.00 × 1.80 × 1.25 = ₹225.00
     */
    public function test_calculates_assessment_example_accurately(): void
    {
        $result = $this->engine->calculate(
            planCode: 'PLUS',
            sumInsured: 300000,
            destination: 'INTERNATIONAL',
            dateOfBirth: '1998-04-10',
            tripStart: '2026-11-10',
            tripEnd: '2026-11-14'
        );

        $this->assertEquals('225.00', $result['final_premium']);
        $this->assertEquals(5, $result['travel_days']);
        $this->assertEquals(28, $result['age_at_trip_start']);
        $this->assertEquals('1.00', $result['breakdown']['age_multiplier']);
        $this->assertEquals('1.80', $result['breakdown']['destination_multiplier']);
        $this->assertEquals('1.25', $result['breakdown']['coverage_multiplier']);
    }

    public function test_age_multipliers_by_tier(): void
    {
        // 0–17: 1.10
        $this->assertEquals('1.10', $this->engine->getAgeMultiplier(16));
        $this->assertEquals('1.10', $this->engine->getAgeMultiplier(0));

        // 18–60: 1.00
        $this->assertEquals('1.00', $this->engine->getAgeMultiplier(18));
        $this->assertEquals('1.00', $this->engine->getAgeMultiplier(45));
        $this->assertEquals('1.00', $this->engine->getAgeMultiplier(60));

        // 61–70: 1.50
        $this->assertEquals('1.50', $this->engine->getAgeMultiplier(61));
        $this->assertEquals('1.50', $this->engine->getAgeMultiplier(70));

        // 71+: 2.00
        $this->assertEquals('2.00', $this->engine->getAgeMultiplier(71));
        $this->assertEquals('2.00', $this->engine->getAgeMultiplier(85));
    }

    public function test_rejects_unsupported_plan_code(): void
    {
        $this->expectException(InvalidPricingConfigurationException::class);
        $this->expectExceptionMessage("Unsupported plan code: 'GOLD'");

        $this->engine->calculate(
            planCode: 'GOLD',
            sumInsured: 300000,
            destination: 'INTERNATIONAL',
            dateOfBirth: '1995-01-01',
            tripStart: '2026-11-01',
            tripEnd: '2026-11-05'
        );
    }

    public function test_rejects_unsupported_sum_insured(): void
    {
        $this->expectException(InvalidPricingConfigurationException::class);
        $this->expectExceptionMessage("Unsupported sum insured: '200000'");

        $this->engine->calculate(
            planCode: 'BASIC',
            sumInsured: 200000,
            destination: 'DOMESTIC',
            dateOfBirth: '1995-01-01',
            tripStart: '2026-11-01',
            tripEnd: '2026-11-05'
        );
    }

    public function test_rejects_trip_end_before_trip_start(): void
    {
        $this->expectException(InvalidPricingConfigurationException::class);
        $this->expectExceptionMessage("Trip end date (2026-11-01) cannot be earlier than trip start date (2026-11-05)");

        $this->engine->calculate(
            planCode: 'BASIC',
            sumInsured: 100000,
            destination: 'DOMESTIC',
            dateOfBirth: '1995-01-01',
            tripStart: '2026-11-05',
            tripEnd: '2026-11-01'
        );
    }

    public function test_single_day_trip_calculates_minimum_one_day(): void
    {
        // 1 day, BASIC (12), DOMESTIC (1.00), Age 30 (1.00), Coverage 100k (1.00) => 12.00
        $result = $this->engine->calculate(
            planCode: 'BASIC',
            sumInsured: 100000,
            destination: 'DOMESTIC',
            dateOfBirth: '1995-01-01',
            tripStart: '2026-11-01',
            tripEnd: '2026-11-01'
        );

        $this->assertEquals(1, $result['travel_days']);
        $this->assertEquals('12.00', $result['final_premium']);
    }
}
