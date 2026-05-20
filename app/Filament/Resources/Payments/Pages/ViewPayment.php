<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Domain\Payments\Actions\ConfirmPayment;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Models\Payment;
use App\Filament\Resources\Payments\PaymentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markAsPaid')
                ->label('Mark as Paid')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm Manual Payment')
                ->modalDescription('This will mark the payment as succeeded and transition the application to Payment Completed. Use this only when payment was confirmed outside Stripe (e.g. bank transfer).')
                ->visible(fn (Payment $record): bool => $record->status !== PaymentStatus::Succeeded)
                ->action(function (Payment $record): void {
                    (new ConfirmPayment)->execute($record, auth()->user());
                    $this->refreshFormData(['status', 'succeeded_at']);
                })
                ->successNotificationTitle('Payment marked as paid'),
        ];
    }
}
