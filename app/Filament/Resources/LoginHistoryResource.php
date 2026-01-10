<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoginHistoryResource\Pages;
use App\Repositories\AdminRepository;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class LoginHistoryResource extends Resource
{
    protected static ?string $model = \App\Models\User::class; // Dummy model for Filament

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Login History';

    protected static ?string $pluralLabel = 'Login History';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    /**
     * Override to prevent Filament from trying to use Eloquent queries
     */
    public static function getEloquentQuery(): Builder
    {
        return \App\Models\User::query()->whereRaw('1 = 0'); // Empty query
    }

    /**
     * Override to prevent Filament from trying to resolve a model
     */
    public static function getModel(): string
    {
        return \App\Models\User::class;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Read-only form since this is API data
                Forms\Components\Section::make('Login History Information')
                    ->schema([
                        Forms\Components\TextInput::make('user_id')
                            ->label('User ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('user_email')
                            ->label('User Email')
                            ->disabled(),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),
                        Forms\Components\Textarea::make('user_agent')
                            ->label('User Agent')
                            ->disabled()
                            ->rows(3),
                        Forms\Components\DateTimePicker::make('logged_in_at')
                            ->label('Logged In At')
                            ->disabled()
                            ->placeholder('Not available')
                            ->displayFormat('Y-m-d H:i:s')
                            ->nullable()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('logged_out_at')
                            ->label('Logged Out At')
                            ->disabled()
                            ->placeholder('Not available')
                            ->displayFormat('Y-m-d H:i:s')
                            ->nullable()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('device_name')
                            ->label('Device Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('location')
                            ->label('Location')
                            ->disabled(),
                        Forms\Components\TextInput::make('action')
                            ->label('Action')
                            ->disabled(),
                        Forms\Components\Toggle::make('successful')
                            ->label('Successful')
                            ->disabled(),
                        Forms\Components\TextInput::make('failure_reason')
                            ->label('Failure Reason')
                            ->disabled()
                            ->visible(fn ($record) => !empty($record?->failure_reason)),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user_email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('device_name')
                    ->label('Device')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('logged_in_at')
                    ->label('Logged In')
                    ->sortable(false) // Disable sorting since we're using API data - sorting happens on API side
                    ->placeholder('N/A')
                    ->formatStateUsing(function ($state) {
                        // Handle null, empty, or invalid date values
                        if (empty($state) || $state === 'N/A' || $state === null) {
                            return 'N/A';
                        }
                        
                        // If it's already a formatted string, return as-is
                        if (is_string($state) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $state)) {
                            return 'N/A';
                        }
                        
                        try {
                            // Try to parse and format the date
                            return \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s');
                        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                            return 'Invalid Date';
                        } catch (\Exception $e) {
                            return 'N/A';
                        }
                    }),
                Tables\Columns\TextColumn::make('logged_out_at')
                    ->label('Logged Out')
                    ->sortable(false) // Disable sorting since we're using API data
                    ->placeholder('N/A')
                    ->toggleable()
                    ->formatStateUsing(function ($state) {
                        // Handle null, empty, or invalid date values
                        if (empty($state) || $state === 'N/A' || $state === null) {
                            return 'N/A';
                        }
                        
                        // If it's already a formatted string, return as-is
                        if (is_string($state) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $state)) {
                            return 'N/A';
                        }
                        
                        try {
                            // Try to parse and format the date
                            return \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s');
                        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                            return 'Invalid Date';
                        } catch (\Exception $e) {
                            return 'N/A';
                        }
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable()
                    ->default(false),
            ])
            ->defaultSort('id', 'desc') // Sort by ID instead of date to avoid Carbon parsing issues
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('logged_in_from')
                            ->label('Logged In From'),
                        Forms\Components\DatePicker::make('logged_in_to')
                            ->label('Logged In To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // This won't actually filter the query since we're using API data
                        // The filtering will be done in the page
                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(function ($record) {
                        // Get record data as array
                        $recordData = is_object($record) && method_exists($record, 'getAttributes') 
                            ? $record->getAttributes() 
                            : (is_object($record) ? (array) $record : $record);
                        
                        // Use a unique identifier for the record
                        $recordId = $recordData['id'] ?? $recordData['user_id'] ?? md5(json_encode($recordData));
                        
                        return static::getUrl('view', [
                            'record' => base64_encode(json_encode([
                                'id' => $recordId,
                                'data' => $recordData,
                            ]))
                        ]);
                    }),
            ])
            ->bulkActions([
                // No bulk actions for login history
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
            'index' => Pages\ListLoginHistory::route('/'),
            'view' => Pages\ViewLoginHistory::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Login history is read-only
    }
}