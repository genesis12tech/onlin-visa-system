<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\GenerateTrackingNumber;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTrackingNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_tracking_number_with_correct_prefix(): void
    {
        $number = app(GenerateTrackingNumber::class)->execute();

        $this->assertStringStartsWith('VA-'.now()->year.'-', $number);
    }

    public function test_it_generates_unique_numbers(): void
    {
        $numbers = collect(range(1, 10))
            ->map(fn () => app(GenerateTrackingNumber::class)->execute());

        $this->assertCount(10, $numbers->unique());
    }

    public function test_it_retries_if_tracking_number_already_exists(): void
    {
        VisaApplication::factory()->create(['tracking_number' => 'VA-'.now()->year.'-AAAAAA']);

        $number = app(GenerateTrackingNumber::class)->execute();

        $this->assertNotEquals('VA-'.now()->year.'-AAAAAA', $number);
    }
}
