<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Identity\Actions\AssignRole;
use App\Domain\Identity\Actions\SuspendUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(','),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => $state->color()),
                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->date()
                    ->sortable()
                    ->placeholder('Not verified'),
                TextColumn::make('created_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),

                Action::make('assign_role')
                    ->authorize(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
                    ->icon('heroicon-o-user-circle')
                    ->color('primary')
                    ->form([
                        Select::make('role')
                            ->options(Role::orderBy('name')->pluck('name', 'name'))
                            ->required(),
                    ])
                    ->action(fn (User $record, array $data): User => AssignRole::run($record, $data['role'], auth()->user())
                    ),

                Action::make('suspend')
                    ->authorize(fn (User $record): bool => (auth()->user()?->hasRole('super_admin') ?? false)
                        && ! $record->hasRole('super_admin')
                    )
                    ->visible(fn (User $record): bool => $record->status !== UserStatus::Suspended
                    )
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record): User => SuspendUser::run($record, auth()->user())
                    ),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
