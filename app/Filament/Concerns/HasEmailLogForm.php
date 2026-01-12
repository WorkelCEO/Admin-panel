<?php

namespace App\Filament\Concerns;

use Filament\Forms;
use Filament\Schemas\Schema;

/**
 * Shared form configuration for Email Log resources
 */
trait HasEmailLogForm
{
    public static function emailLogForm(Schema $schema): Schema
    {
        return $schema->components([
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
}
