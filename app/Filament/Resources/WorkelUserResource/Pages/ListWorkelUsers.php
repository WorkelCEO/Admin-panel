<?php

namespace App\Filament\Resources\WorkelUserResource\Pages;

use App\Filament\Resources\WorkelUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkelUsers extends ListRecords
{
    protected static string $resource = WorkelUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
