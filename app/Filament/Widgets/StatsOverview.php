<?php

namespace App\Filament\Widgets;

use App\Repositories\AdminRepository;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class StatsOverview extends BaseWidget
{
    protected int | string | array $columnSpan = '6';

    protected function getStats(): array
    {
        try {
            $repository = app(AdminRepository::class);

            // Get counts from API
            $allParams = ['exclude_admin' => true, 'per_page' => 1];
            $allResponse = $repository->getUsers($allParams);
            $totalUsers = $allResponse['meta']['total'] ?? 0;

            $appParams = ['api_type' => 'App', 'per_page' => 1];
            $appResponse = $repository->getUsers($appParams);
            $appUsers = $appResponse['meta']['total'] ?? 0;

            $clientParams = ['api_type' => 'Client', 'per_page' => 1];
            $clientResponse = $repository->getUsers($clientParams);
            $clientUsers = $clientResponse['meta']['total'] ?? 0;

            // For today's users, fetch with date filter if supported
            // Note: This may require API support for date filtering
            $todayParams = ['exclude_admin' => true, 'created_at' => today()->toDateString(), 'per_page' => 1];
            $todayResponse = $repository->getUsers($todayParams);
            $todayUsers = $todayResponse['meta']['total'] ?? 0;

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
        } catch (\Exception $e) {
            // Fallback if API fails
            return [
                Stat::make('Total Users', 0)
                    ->description('API unavailable')
                    ->descriptionIcon('heroicon-o-users')
                    ->color('gray'),
                Stat::make('App Users', 0)
                    ->description('API unavailable')
                    ->descriptionIcon('heroicon-o-device-phone-mobile')
                    ->color('gray'),
                Stat::make('Client Users', 0)
                    ->description('API unavailable')
                    ->descriptionIcon('heroicon-o-computer-desktop')
                    ->color('gray'),
                Stat::make('Users Today', 0)
                    ->description('API unavailable')
                    ->descriptionIcon('heroicon-o-calendar')
                    ->color('gray'),
            ];
        }
    }
}
