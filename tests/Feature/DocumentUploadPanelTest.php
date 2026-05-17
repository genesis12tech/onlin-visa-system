<?php

namespace Tests\Feature;

use App\Domain\Documents\Enums\DocumentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentUploadPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_status_has_human_readable_labels(): void
    {
        $this->assertSame('Pending', DocumentStatus::Pending->label());
        $this->assertSame('Uploaded', DocumentStatus::Uploaded->label());
        $this->assertSame('Scanning', DocumentStatus::PendingScan->label());
        $this->assertSame('Under Review', DocumentStatus::UnderReview->label());
        $this->assertSame('Accepted', DocumentStatus::Accepted->label());
        $this->assertSame('Rejected', DocumentStatus::Rejected->label());
        $this->assertSame('Security Failed', DocumentStatus::Infected->label());
    }
}
