<?php
 
namespace App\Filament\Pages;

use App\Filament\Widgets\StatsOverview;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }
    
    public function getColumns(): array | int
    {
        return 6;
    }
}
