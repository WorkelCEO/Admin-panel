<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkelUserResource\Pages;
use App\Filament\Resources\WorkelUserResource\RelationManagers;
use App\Models\WorkelUser;
use App\Repositories\AdminRepository;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkelUserResource extends Resource
{
    protected static ?string $model = null; // Not using Eloquent model

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * Override to prevent Filament from trying to use Eloquent queries
     */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Return a dummy query since we're using API data, not Eloquent models
        return \App\Models\WorkelUser::query()->whereRaw('1 = 0'); // Empty query
    }

    /**
     * Override to prevent Filament from trying to resolve a model
     * Return WorkelUser model as a fallback since we're using API data
     */
    public static function getModel(): string
    {
        return WorkelUser::class; // Use WorkelUser model as fallback
    }

    /**
     * Get the record title attribute for display
     */
    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('John Doe'),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->required()
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->minLength(8)
                            ->dehydrated(fn($state) => filled($state))
                            ->helperText('Leave blank to keep current password'),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('+1234567890'),
                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('123 Main St, New York, NY 10001'),
                        Forms\Components\Select::make('system_role')
                            ->label('System Role')
                            ->options([
                                'user' => 'User',
                                'admin' => 'Admin',
                                'super_admin' => 'Super Admin',
                            ])
                            ->default('user')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('system_role')
                    ->label('Role')
                    ->colors([
                        'primary' => 'user',
                        'success' => 'admin',
                        'danger' => 'super_admin',
                    ])
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Source')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('api_type')
                    ->label('API Type')
                    ->searchable()
                    ->sortable()
                    ->colors([
                        'success' => 'App',
                        'warning' => 'Client',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'suspended',
                    ])
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('api_type')
                    ->label('API Type')
                    ->options([
                        'App' => 'App',
                        'Client' => 'Client',
                    ]),
                Tables\Filters\SelectFilter::make('system_role')
                    ->label('Role')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Admin',
                        'super_admin' => 'Super Admin',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('change_role')
                    ->label('Change Role')
                    ->icon('heroicon-o-shield-check')
                    ->form([
                        Forms\Components\Select::make('system_role')
                            ->label('System Role')
                            ->options([
                                'user' => 'User',
                                'admin' => 'Admin',
                                'super_admin' => 'Super Admin',
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $repository = app(AdminRepository::class);
                        $userId = $record instanceof \Illuminate\Database\Eloquent\Model 
                            ? $record->getKey() 
                            : ($record['id'] ?? null);
                        
                        if (!$userId) {
                            Notification::make()
                                ->title('Invalid user ID')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        $response = $repository->changeUserRole($userId, $data['system_role']);
                        
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
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete User')
                    ->modalDescription('Are you sure you want to delete this user? This action cannot be undone.')
                    ->action(function ($record) {
                        $repository = app(AdminRepository::class);
                        $userId = $record instanceof \Illuminate\Database\Eloquent\Model 
                            ? $record->getKey() 
                            : ($record['id'] ?? null);
                        
                        if (!$userId) {
                            Notification::make()
                                ->title('Invalid user ID')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        if ($repository->deleteUser($userId)) {
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
                // Bulk actions removed since we're using API
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
            'index' => Pages\ListWorkelUsers::route('/'),
            'edit' => Pages\EditWorkelUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Users are created through the API, not through Filament
    }
}
