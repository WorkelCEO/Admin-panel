<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use App\Models\WorkelUser;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue for the Last 30 Days';
    // You can use the "bar" or "line" chart type depending on preference
    protected int | string | array $columnSpan = '3';

    protected function getData(): array
    {
        // Query the revenue data for the last 30 days
        $revenues = WorkelUser::selectRaw('DATE(created_at) as date, SUM(subscription_payment_amount) as total_revenue')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Prepare the chart data
        $dates = [];
        $totals = [];

        // Initialize arrays for storing dates and revenue totals
        $dates = [];
        $totals = [];

        // Fill the last 30 days, ensuring every day is accounted for
        for ($i = 30; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;  // Add this date to the dates array

            // If there's revenue for this date, use it, otherwise use 0
            $totals[] = $revenues->get($date)->total_revenue ?? 0;
        }               
        // dd($dates, $totals);
        // Return the data to the chart
        return [
            'labels' => $dates,  // The dates (X-axis labels)
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' =>  $totals, // The corresponding revenue values (Y-axis values)
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    // 'borderWidth' => 1,
                ],
            ],
        ];
    }
    protected function getType(): string
    {
        return 'line'; // or 'bar' if you prefer a bar chart
    }

}
