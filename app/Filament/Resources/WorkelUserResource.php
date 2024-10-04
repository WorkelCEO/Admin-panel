<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkelUserResource\Pages;
use App\Filament\Resources\WorkelUserResource\RelationManagers;
use App\Models\WorkelUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WorkelUserResource extends Resource
{
    protected static ?string $model = WorkelUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->placeholder('John Doe'),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->required()
                    ->email(),
                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->required()
                    ->password(),
                Forms\Components\Select::make('verfiy_email')
                    ->label('Verify Email')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                    ])
                    ->default('no'),
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
                Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_type')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_start_date')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_end_date')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_status')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_payment_status')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_payment_method')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_payment_date')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_payment_amount')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_payment_currency')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_payment_transaction_id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_payment_receipt')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListWorkelUsers::route('/'),
            'create' => Pages\CreateWorkelUser::route('/create'),
            'edit' => Pages\EditWorkelUser::route('/{record}/edit'),
        ];
    }
}
