<?php

namespace App\Filament\Widgets;

use App\Models\WorkelUser;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class StatsOverview extends BaseWidget
{
        protected int | string | array $columnSpan = '6';

    protected function getStats(): array
    {
        return [
            Stat::make('Number of User', WorkelUser::count()),
            Stat::make('Number of Subscribers', '0'),
            Stat::make('Number Total Revenue', '0 $'),
        ];
    }
}
