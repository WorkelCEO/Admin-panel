<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use App\Models\User;
use App\Models\WorkelUser;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\LineChartWidget;

class UserCreationChart extends ChartWidget
{
    protected static ?string $heading = 'Users Created per Day';
    protected int | string | array $columnSpan = '3';
    protected function getData(): array
    {
        // Get data for the last 30 days
        $users = WorkelUser::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->take(30)  
            ->get();

        // Prepare the data for the chart
        $dates = $users->pluck('date')->map(function ($date) {
            return Carbon::parse($date)->format('Y-m-d');
        })->toArray();

        $counts = $users->pluck('count')->toArray();

        return [
            'labels' => $dates,   // The X-axis will be the dates
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $counts,  // The Y-axis will show the number of users created
                    'borderColor' => '#4CAF50',  // Line color
                    'backgroundColor' => 'rgba(76, 175, 80, 0.2)',  // Optional background fill
                ],
            ],
        ];
    }
    protected function getType(): string
    {
        return 'bar';
    }
}