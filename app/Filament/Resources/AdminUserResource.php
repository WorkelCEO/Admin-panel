<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminUserResource\Pages;
use App\Repositories\AdminRepository;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminUserResource extends Resource
{
    protected static ?string $model = null; // Not using Eloquent model

    protected static ?string $navigationIcon = 'heroicon-o-users';

    /**
     * Override to prevent Filament from trying to use Eloquent queries
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Return a dummy query since we're using API data, not Eloquent models
        return \App\Models\User::query()->whereRaw('1 = 0'); // Empty query
    }

    /**
     * Override to prevent Filament from trying to resolve a model
     * Return User model as a fallback since we're using API data
     */
    public static function getModel(): string
    {
        return \App\Models\User::class; // Use User model as fallback
    }

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $pluralLabel = 'Users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->minLength(8)
                            ->dehydrated(fn($state) => filled($state)),
                        Forms\Components\Select::make('system_role')
                            ->label('System Role')
                            ->options([
                                'user' => 'User',
                                'admin' => 'Admin',
                                'super_admin' => 'Super Admin',
                            ])
                            ->required()
                            ->default('user'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('system_role')
                    ->label('Role')
                    ->colors([
                        'primary' => 'user',
                        'success' => 'admin',
                        'danger' => 'super_admin',
                    ])
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_super_admin')
                    ->label('Super Admin')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->label('Email Verified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_seen')
                    ->label('Last Seen')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('system_role')
                    ->label('Role')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Admin',
                        'super_admin' => 'Super Admin',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('change_role')
                    ->label('Change Role')
                    ->icon('heroicon-o-shield-check')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('New Role')
                            ->options([
                                'user' => 'User',
                                'admin' => 'Admin',
                                'super_admin' => 'Super Admin',
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $repository = app(AdminRepository::class);
                        $response = $repository->changeUserRole($record['id'], $data['role']);
                        
                        if ($response->isSuccess()) {
                            Notification::make()
                                ->title('User role updated successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to update user role')
                                ->body($response->message ?? 'Please try again.')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn($record) => ($record['status'] ?? 'active') === 'active')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required(),
                        Forms\Components\DateTimePicker::make('suspended_until')
                            ->label('Suspend Until (Optional)')
                            ->nullable(),
                    ])
                    ->action(function ($record, array $data) {
                        $repository = app(AdminRepository::class);
                        $response = $repository->suspendUser(
                            $record['id'],
                            $data['reason'],
                            $data['suspended_until']?->toIso8601String()
                        );
                        
                        if ($response->isSuccess()) {
                            Notification::make()
                                ->title('User suspended successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to suspend user')
                                ->body($response->message ?? 'Please try again.')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('unsuspend')
                    ->label('Unsuspend')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn($record) => ($record['status'] ?? 'active') !== 'active')
                    ->action(function ($record) {
                        $repository = app(AdminRepository::class);
                        $response = $repository->unsuspendUser($record['id']);
                        
                        if ($response->isSuccess()) {
                            Notification::make()
                                ->title('User unsuspended successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to unsuspend user')
                                ->body($response->message ?? 'Please try again.')
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $repository = app(AdminRepository::class);
                        
                        if ($repository->deleteUser($record['id'])) {
                            Notification::make()
                                ->title('User deleted successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to delete user')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Bulk actions can be added here
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
            'index' => Pages\ListAdminUsers::route('/'),
            'view' => Pages\ViewAdminUser::route('/{record}'),
            'edit' => Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Users are created through the API, not through Filament
    }
}
