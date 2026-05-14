<?php

namespace Tests\Unit;

use App\Domain\Applications\Actions\GenerateTrackingNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTrackingNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_reference_matches_pattern(): void
    {
        $reference = (new GenerateTrackingNumber)->execute();

        $this->assertMatchesRegularExpression('/^VA-\d{4}-[A-Z0-9]{6}$/', $reference);
    }

    public function test_generated_reference_contains_current_year(): void
    {
        $reference = (new GenerateTrackingNumber)->execute();

        $this->assertStringContainsString('VA-'.now()->year.'-', $reference);
    }

    public function test_successive_references_are_unique(): void
    {
        $references = array_map(
            fn () => (new GenerateTrackingNumber)->execute(),
            range(1, 10),
        );

        $this->assertCount(10, array_unique($references));
    }
}
