<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use App\Models\WorkelUser;
use Filament\Widgets\ChartWidget;

class UserCreationChart extends ChartWidget
{
    protected static ?string $heading = 'Users Created per Day (Last 30 Days)';
    
    protected int | string | array $columnSpan = '3';

    protected function getData(): array
    {
        // Get data for the last 30 days, excluding admin users
        $startDate = now()->subDays(30)->startOfDay();
        
        // Get users created in the last 30 days, excluding admin users
        // Use raw query to group by date for better performance
        $usersData = WorkelUser::selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->where('created_at', '>=', $startDate)
            ->where('role', '!=', 'admin') // Exclude admin users
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Convert to key-value array for easy lookup
        $users = [];
        foreach ($usersData as $item) {
            $users[$item->date] = (int) $item->count;
        }

        // Prepare arrays for the last 30 days
        $dates = [];
        $counts = [];

        // Fill the last 30 days, ensuring every day is accounted for
        for ($i = 30; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;
            
            // Get count for this date, or 0 if no users created
            $counts[] = $users[$date] ?? 0;
        }

        return [
            'labels' => $dates,
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $counts,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}