<?php

namespace Tests\Feature;

use App\Domain\Applications\Models\ApplicationNote;
use App\Domain\Applications\Models\VisaApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationNoteScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_to_applicant_scope_returns_only_applicant_visible_notes(): void
    {
        $application = VisaApplication::factory()->create();

        ApplicationNote::factory()->create([
            'visa_application_id' => $application->ulid,
            'body' => 'Internal note — officers only.',
            'is_visible_to_applicant' => false,
        ]);

        $visible = ApplicationNote::factory()->create([
            'visa_application_id' => $application->ulid,
            'body' => 'Visible note — applicant sees this.',
            'is_visible_to_applicant' => true,
        ]);

        $results = ApplicationNote::visibleToApplicant()->get();

        $this->assertTrue($results->contains('ulid', $visible->ulid));
        $this->assertFalse($results->contains('body', 'Internal note — officers only.'));
    }

    public function test_internal_notes_are_excluded_by_visible_to_applicant_scope(): void
    {
        $application = VisaApplication::factory()->create();

        $internal = ApplicationNote::factory()->create([
            'visa_application_id' => $application->ulid,
            'body' => 'Fraud review flag — do not share.',
            'is_visible_to_applicant' => false,
        ]);

        $scopedIds = ApplicationNote::visibleToApplicant()->pluck('ulid');

        $this->assertNotContains($internal->ulid, $scopedIds);
    }

    public function test_full_query_without_scope_returns_all_notes(): void
    {
        $application = VisaApplication::factory()->create();

        ApplicationNote::factory()->create([
            'visa_application_id' => $application->ulid,
            'is_visible_to_applicant' => false,
        ]);

        ApplicationNote::factory()->create([
            'visa_application_id' => $application->ulid,
            'is_visible_to_applicant' => true,
        ]);

        $this->assertEquals(2, ApplicationNote::where('visa_application_id', $application->ulid)->count());
    }

    public function test_scope_works_across_multiple_applications(): void
    {
        $appA = VisaApplication::factory()->create();
        $appB = VisaApplication::factory()->create();

        ApplicationNote::factory()->create([
            'visa_application_id' => $appA->ulid,
            'is_visible_to_applicant' => true,
        ]);

        ApplicationNote::factory()->create([
            'visa_application_id' => $appB->ulid,
            'is_visible_to_applicant' => false,
        ]);

        $this->assertEquals(1, ApplicationNote::visibleToApplicant()->count());
    }
}
