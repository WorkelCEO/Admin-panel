<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\EmailLog;
use App\Services\EmailLogsAppApiService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email Logs (App)';

    protected static ?string $pluralLabel = 'Email Logs (App)';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Email Information')
                    ->schema([
                        Forms\Components\TextInput::make('sender_email')
                            ->label('Sender Email')
                            ->email()
                            ->disabled(),
                        Forms\Components\TextInput::make('sender_name')
                            ->label('Sender Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('recipient_email')
                            ->label('Recipient Email')
                            ->email()
                            ->disabled(),
                        Forms\Components\TextInput::make('recipient_name')
                            ->label('Recipient Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject')
                            ->disabled()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Type')
                    ->schema([
                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),
                        Forms\Components\TextInput::make('email_type')
                            ->label('Email Type')
                            ->disabled(),
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->disabled()
                            ->columnSpanFull()
                            ->visible(fn($record) => $record?->error_message !== null),
                    ])->columns(2),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => !empty($record?->metadata)),

                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\TextInput::make('sent_at')
                            ->label('Sent At')
                            ->disabled(),
                        Forms\Components\TextInput::make('created_at')
                            ->label('Created At')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
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
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (EmailLog $record) {
                        $service = app(EmailLogsAppApiService::class);
                        
                        if ($service->deleteEmailLog($record->id)) {
                            Notification::make()
                                ->title('Email log deleted successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to delete email log')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Bulk actions are limited due to API constraints
            ])
            ->poll(null) // Disabled auto-refresh for better performance
            ->deferLoading(); // Deferred loading enabled
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }

    /**
     * Disable create and edit actions as this is a read-only resource from external API
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}

