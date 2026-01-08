<?php

namespace App\Filament\Concerns;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;

/**
 * Shared table configuration for Email Log resources
 */
trait HasEmailLogTable
{
    public static function emailLogTable(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Click to copy'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'success',
                        'danger' => 'error',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'success',
                        'heroicon-o-x-circle' => 'error',
                    ])
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email_type')
                    ->label('Type')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'verification' => 'Verification',
                        'project_invitation' => 'Project Invitation',
                        'workspace_invitation' => 'Workspace Invitation',
                        'workspace_invitation_by_email' => 'Workspace Invitation (Email)',
                        'forgot_password' => 'Password Reset',
                        'unread_chat' => 'Unread Chat',
                        'user_registration_notification' => 'Registration',
                        default => str_replace('_', ' ', ucfirst($state)),
                    })
                    ->colors([
                        'primary' => 'verification',
                        'success' => 'project_invitation',
                        'info' => 'workspace_invitation',
                        'warning' => 'forgot_password',
                        'danger' => 'user_registration_notification',
                    ]),

                TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->limit(30),

                TextColumn::make('sender_email')
                    ->label('Sender')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn($record) => $record->subject),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->error_message)
                    ->visible(fn() => request()->get('tableFilters.status.value') === 'error')
                    ->toggleable(),

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'success' => 'Success',
                        'error' => 'Error',
                    ])
                    ->placeholder('All Statuses'),

                SelectFilter::make('email_type')
                    ->label('Email Type')
                    ->options([
                        'verification' => 'Verification',
                        'project_invitation' => 'Project Invitation',
                        'workspace_invitation' => 'Workspace Invitation',
                        'workspace_invitation_by_email' => 'Workspace Invitation (Email)',
                        'forgot_password' => 'Password Reset',
                        'unread_chat' => 'Unread Chat',
                        'user_registration_notification' => 'Registration Notification',
                    ])
                    ->placeholder('All Types'),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('From Date')
                            ->placeholder('YYYY-MM-DD'),
                        DatePicker::make('date_to')
                            ->label('To Date')
                            ->placeholder('YYYY-MM-DD'),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['date_from'] && !$data['date_to']) {
                            return null;
                        }

                        $from = $data['date_from'] ?? 'start';
                        $to = $data['date_to'] ?? 'end';

                        return "Date: {$from} to {$to}";
                    }),

                Filter::make('recipient_email')
                    ->form([
                        Forms\Components\TextInput::make('recipient_email')
                            ->label('Recipient Email')
                            ->email()
                            ->placeholder('user@example.com'),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['recipient_email']) {
                            return null;
                        }

                        return 'Recipient: ' . $data['recipient_email'];
                    }),
            ])
            ->poll(null) // Disabled auto-refresh for better performance
            ->deferLoading(); // Deferred loading enabled
    }
}
