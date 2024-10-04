<?php
 
namespace App\Filament\Pages;

use Filament\Forms\Components\Grid;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UserCreationChart;
use App\Filament\Widgets\SubscribsStatesChart;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getWidgets(): array
    {
        // Two columns layout
        return [
                StatsOverview::class,
                UserCreationChart::class,
                RevenueChart::class
                // SubscribsStatesChart::class
        ];
    }
    public function getColumns(): int | string | array
    {
        return 6;
    }
}
