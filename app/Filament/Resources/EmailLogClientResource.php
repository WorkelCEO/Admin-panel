<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasEmailLogForm;
use App\Filament\Concerns\HasEmailLogTable;
use App\Filament\Resources\EmailLogClientResource\Pages;
use App\Models\EmailLog;
use App\Repositories\EmailLogRepository;
use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailLogClientResource extends Resource
{
    use HasEmailLogForm;
    use HasEmailLogTable;

    protected static ?string $model = EmailLog::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationLabel = 'Email Logs (Client)';

    protected static ?string $pluralLabel = 'Email Logs (Client)';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return self::emailLogForm($schema);
    }

    public static function table(Table $table): Table
    {
        return self::emailLogTable($table)
            ->actions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (EmailLog $record) {
                        $repository = app(EmailLogRepository::class);
                        
                        if ($repository->deleteEmailLog('client', $record->id)) {
                            Notification::make()
                                ->title('Email log deleted successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to delete email log')
                                ->body('Please try again or contact support if the problem persists.')
                                ->danger()
                                ->actions([
                                    Notification::make('retry')
                                        ->label('Retry')
                                        ->action(fn() => $repository->deleteEmailLog('client', $record->id)),
                                ])
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Bulk actions are limited due to API constraints
            ]);
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
            'index' => Pages\ListEmailLogClients::route('/'),
            'view' => Pages\ViewEmailLogClient::route('/{record}'),
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

