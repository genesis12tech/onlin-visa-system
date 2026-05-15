<?php

namespace Tests\Unit;

use App\Domain\Applications\Enums\ApplicationStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use Tests\TestCase;

class PaymentStatusEnumTest extends TestCase
{
    public function test_application_status_has_payment_pending(): void
    {
        $this->assertEquals('payment_pending', ApplicationStatus::PaymentPending->value);
    }

    public function test_application_status_has_payment_completed(): void
    {
        $this->assertEquals('payment_completed', ApplicationStatus::PaymentCompleted->value);
    }

    public function test_payment_status_has_expected_cases(): void
    {
        $this->assertEquals('pending', PaymentStatus::Pending->value);
        $this->assertEquals('processing', PaymentStatus::Processing->value);
        $this->assertEquals('succeeded', PaymentStatus::Succeeded->value);
        $this->assertEquals('failed', PaymentStatus::Failed->value);
        $this->assertEquals('refunded', PaymentStatus::Refunded->value);
        $this->assertEquals('partially_refunded', PaymentStatus::PartiallyRefunded->value);
    }
}
