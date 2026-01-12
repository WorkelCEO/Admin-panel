<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BackupResource\Pages;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BackupResource extends Resource
{
    protected static ?string $model = null;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'Backups';

    protected static ?string $pluralLabel = 'Backups';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\Section::make('Backup Information')
                    ->schema([
                        Forms\Components\TextInput::make('filename')
                            ->label('Filename')
                            ->disabled(),
                        Forms\Components\TextInput::make('size')
                            ->label('Size')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'processing',
                        'danger' => 'failed',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'completed' => 'Completed',
                        'processing' => 'Processing',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        $repository = app(AdminRepository::class);
                        $response = $repository->downloadBackup($record['id']);
                        
                        if ($response->isSuccess()) {
                            Notification::make()
                                ->title('Backup download initiated')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to download backup')
                                ->body($response->message ?? 'Please try again.')
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\Action::make('import')
                    ->label('Import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Checkbox::make('confirm')
                            ->label('I confirm I want to import this backup')
                            ->required(),
                        Forms\Components\Checkbox::make('restore_files')
                            ->label('Restore files'),
                    ])
                    ->action(function ($record, array $data) {
                        $repository = app(AdminRepository::class);
                        $response = $repository->importBackup(
                            $record['id'],
                            $data['confirm'] ?? false,
                            $data['restore_files'] ?? false
                        );
                        
                        if ($response->isSuccess()) {
                            Notification::make()
                                ->title('Backup import initiated')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to import backup')
                                ->body($response->message ?? 'Please try again.')
                                ->danger()
                                ->send();
                        }
                    }),
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $repository = app(AdminRepository::class);
                        
                        if ($repository->deleteBackup($record['id'])) {
                            Notification::make()
                                ->title('Backup deleted successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to delete backup')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->poll(null)
            ->deferLoading();
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
            'index' => Pages\ListBackups::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Backups are created through actions
    }
}
