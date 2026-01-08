<?php

namespace App\Filament\Resources\WorkelUserResource\Pages;

use App\Filament\Resources\WorkelUserResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWorkelUsers extends ListRecords
{
    protected static string $resource = WorkelUserResource::class;

    public ?string $activeTab = 'all';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Users')
                ->badge(fn() => \App\Models\WorkelUser::where('role', '!=', 'admin')->count()),
            'app' => Tab::make('App')
                ->badge(fn() => \App\Models\WorkelUser::where('role', '!=', 'admin')->where('api_type', 'App')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('api_type', 'App')),
            'client' => Tab::make('Client')
                ->badge(fn() => \App\Models\WorkelUser::where('role', '!=', 'admin')->where('api_type', 'Client')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('api_type', 'Client')),
        ];
    }
}
