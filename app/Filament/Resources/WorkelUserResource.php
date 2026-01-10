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
                            ->dehydrated(fn($state) => filled($state)),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->placeholder('1234567890'),
                        Forms\Components\Textarea::make('address')
                            ->label('Address')
                            ->placeholder('123 Main St, New York, NY 10001'),
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options([
                                'user' => 'User',
                                'admin' => 'Admin',
                            ])
                            ->default('user'),
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
                Tables\Columns\TextColumn::make('role')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('api_type')
                    ->label('API Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn($record): string => match ($record['api_type'] ?? null) {
                        'App' => 'success',
                        'Client' => 'danger',
                        default => 'gray',
                    }),
                // Tables\Columns\TextColumn::make('status')
                //     ->searchable()
                //     ->sortable(),
                // Table    s\Columns\TextColumn::make('subscription_type')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_start_date')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_end_date')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_status')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_payment_status')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_payment_method')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_payment_date')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_payment_amount')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_payment_currency')
                //     ->searchable()
                //     ->sortable(),
                // Tables\Columns\TextColumn::make('subscription_payment_transaction_id')
                //     ->searchable()
                //     ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('api_type')
                    ->label('API Type')
                    ->options([
                        'App' => 'App',
                        'Client' => 'Client',
                    ]),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'user' => 'User',
                        'admin' => 'Admin',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
                // Bulk actions removed since we're using API
            ])
            ->poll(null)
            ->deferLoading();
    }
    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
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
