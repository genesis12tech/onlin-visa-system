<?php

namespace App\Filament\Resources\FormTemplates\Pages;

use App\Domain\Applications\Actions\PublishFormTemplate;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditFormTemplate extends EditRecord
{
    protected static string $resource = FormTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Publish Form Template')
                ->modalDescription('Publishing is irreversible. The schema becomes immutable and the previous active version for this visa type will be archived.')
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false)
                ->visible(fn (): bool => $this->record->published_at === null)
                ->action(function (): void {
                    try {
                        (new PublishFormTemplate)->execute($this->record);
                        Notification::make()->success()->title('Template published')->send();
                        $this->refreshFormData(['published_at', 'is_active']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title('Cannot publish')->body($e->getMessage())->send();
                    }
                }),

            DeleteAction::make()
                ->authorize(fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false)
                ->visible(fn (): bool => $this->record->published_at === null),
        ];
    }
}
