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
        $totalUsers = WorkelUser::where('role', '!=', 'admin')->count();
        $appUsers = WorkelUser::where('api_type', 'App')->count();
        $clientUsers = WorkelUser::where('api_type', 'Client')->count(); // Show all Client users, regardless of role
        $todayUsers = WorkelUser::where('role', '!=', 'admin')
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->description('Excluding admin users')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            Stat::make('App Users', $appUsers)
                ->description('Users from App API')
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->color('success'),
            Stat::make('Client Users', $clientUsers)
                ->description('All users from Client API')
                ->descriptionIcon('heroicon-o-computer-desktop')
                ->color('warning'),
            Stat::make('Users Today', $todayUsers)
                ->description('Created today')
                ->descriptionIcon('heroicon-o-calendar')
                ->color('info'),
        ];
    }
}
