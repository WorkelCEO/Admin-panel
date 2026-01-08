<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasEmailLogForm;
use App\Filament\Concerns\HasEmailLogTable;
use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\EmailLog;
use App\Repositories\EmailLogRepository;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailLogResource extends Resource
{
    use HasEmailLogForm;
    use HasEmailLogTable;

    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Email Logs (App)';

    protected static ?string $pluralLabel = 'Email Logs (App)';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return self::emailLogForm($form);
    }

    public static function table(Table $table): Table
    {
        return self::emailLogTable($table)
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function (EmailLog $record) {
                        $repository = app(EmailLogRepository::class);
                        
                        if ($repository->deleteEmailLog('app', $record->id)) {
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
                                        ->action(fn() => $repository->deleteEmailLog('app', $record->id)),
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

