<?php

namespace App\Domain\Payments\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReceiptPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $invoiceUlid) {}

    public function handle(): void
    {
        // Implemented in Task 9
    }
}
