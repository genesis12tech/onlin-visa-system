<?php

namespace Tests\Unit;

use App\Domain\Applications\Models\VisaApplication;
use App\Domain\Applications\Models\VisaType;
use App\Domain\Identity\Models\Country;
use App\Domain\Payments\Actions\CalculateApplicationFee;
use App\Domain\Payments\Models\VisaFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateApplicationFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sums_all_active_current_fees(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Application Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Processing Fee',
            'amount' => 2000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $this->assertEquals(7000, $result['total_amount']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertCount(2, $result['items']);
    }

    public function test_excludes_inactive_fees(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Active Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Inactive Fee',
            'amount' => 3000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => false,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $this->assertEquals(5000, $result['total_amount']);
        $this->assertCount(1, $result['items']);
    }

    public function test_excludes_expired_fees(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Expired Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subMonth(),
            'effective_to' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active fees found');

        (new CalculateApplicationFee)->execute($this->makeApplication($visaType));
    }

    public function test_throws_when_no_fees_exist(): void
    {
        $visaType = $this->makeVisaType();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active fees found');

        (new CalculateApplicationFee)->execute($this->makeApplication($visaType));
    }

    public function test_items_contain_fee_snapshot_fields(): void
    {
        $visaType = $this->makeVisaType();

        $fee = VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Visa Fee',
            'amount' => 8000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $item = $result['items']->first();

        $this->assertEquals($fee->ulid, $item['visa_fee_id']);
        $this->assertEquals('Visa Fee', $item['description']);
        $this->assertEquals(8000, $item['unit_amount']);
        $this->assertEquals(1, $item['quantity']);
    }

    public function test_throws_when_fees_have_mixed_currencies(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'USD Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'EUR Fee',
            'amount' => 3000,
            'currency' => 'EUR',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mixed currencies are not supported');

        (new CalculateApplicationFee)->execute($this->makeApplication($visaType));
    }

    public function test_excludes_priority_fees_by_default(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Application Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'is_priority' => false,
        ]);

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Priority Processing',
            'amount' => 3000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'is_priority' => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $this->assertEquals(5000, $result['total_amount']);
        $this->assertCount(1, $result['items']);
    }

    public function test_includes_priority_fees_when_priority_is_true(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Application Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'is_priority' => false,
        ]);

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Priority Processing',
            'amount' => 3000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'is_priority' => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType), priority: true);

        $this->assertEquals(8000, $result['total_amount']);
        $this->assertCount(2, $result['items']);
    }

    public function test_has_priority_option_is_true_when_priority_fee_exists(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Application Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Priority Processing',
            'amount' => 3000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
            'is_priority' => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $this->assertTrue($result['has_priority_option']);
    }

    public function test_has_priority_option_is_false_when_no_priority_fee(): void
    {
        $visaType = $this->makeVisaType();

        VisaFee::create([
            'visa_type_id' => $visaType->ulid,
            'name' => 'Application Fee',
            'amount' => 5000,
            'currency' => 'USD',
            'applicant_type' => 'all',
            'effective_from' => now()->subDay(),
            'is_active' => true,
        ]);

        $result = (new CalculateApplicationFee)->execute($this->makeApplication($visaType));

        $this->assertFalse($result['has_priority_option']);
    }

    private function makeVisaType(): VisaType
    {
        $country = Country::create(['name' => 'Calcu', 'iso2' => 'CL', 'iso3' => 'CLC']);

        return VisaType::create([
            'name' => 'Tourist',
            'code' => 'TOURIST_CALC_'.uniqid(),
            'country_id' => $country->id,
            'processing_days' => 3,
            'validity_days' => 30,
        ]);
    }

    private function makeApplication(VisaType $visaType): VisaApplication
    {
        $application = new VisaApplication;
        $application->visa_type_id = $visaType->ulid;
        $application->exists = true;

        return $application;
    }
}
