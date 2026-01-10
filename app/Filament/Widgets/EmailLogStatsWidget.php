<?php

namespace App\Filament\Widgets;

use App\Exceptions\ApiException;
use App\Repositories\EmailLogRepository;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmailLogStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = null;

    protected EmailLogRepository $repository;

    public function boot(EmailLogRepository $repository): void
    {
        $this->repository = $repository;
    }

    protected function getStats(): array
    {
        try {
        // Get statistics for last 30 days
            $stats = $this->repository->getStatistics('app', [
            'date_from' => now()->subDays(30)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        if (empty($stats)) {
                return $this->getEmptyStats('No data available');
        }

        $total = $stats['total'] ?? 0;
        $success = $stats['success'] ?? 0;
        $error = $stats['error'] ?? 0;
        $successRate = $stats['success_rate'] ?? 0;

        return [
            Stat::make('Total Emails (30 days)', number_format($total))
                ->description('All emails sent in the last 30 days')
                ->descriptionIcon('heroicon-m-envelope')
                ->color('primary')
                ->chart($this->getChartData($stats['by_date'] ?? [])),

            Stat::make('Successful Emails', number_format($success))
                ->description(sprintf('%.1f%% success rate', $successRate))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart($this->getSuccessChartData($stats['by_date'] ?? [])),

            Stat::make('Failed Emails', number_format($error))
                ->description(sprintf('%d errors in last 30 days', $error))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->chart($this->getErrorChartData($stats['by_date'] ?? [])),

            Stat::make('Success Rate', sprintf('%.1f%%', $successRate))
                ->description('Overall delivery success rate')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($successRate >= 90 ? 'success' : ($successRate >= 70 ? 'warning' : 'danger')),
        ];
        } catch (ApiException $e) {
            return $this->getEmptyStats('API connection failed');
        }
    }

    /**
     * Get empty stats when API is unavailable
     */
    protected function getEmptyStats(string $reason = 'Unable to fetch data'): array
    {
        return [
            Stat::make('Total Emails', 'N/A')
                ->description($reason)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('gray'),

            Stat::make('Successful Emails', 'N/A')
                ->description($reason)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('gray'),

            Stat::make('Failed Emails', 'N/A')
                ->description($reason)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('gray'),

            Stat::make('Success Rate', 'N/A')
                ->description($reason)
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('gray'),
        ];
    }

    /**
     * Get chart data for total emails by date
     */
    protected function getChartData(array $byDate): array
    {
        if (empty($byDate)) {
            return [];
        }

        // Get last 7 days for chart
        $last7Days = array_slice($byDate, -7, 7, true);
        
        return array_values($last7Days);
    }

    /**
     * Get success chart data (mock - would need separate API endpoint)
     */
    protected function getSuccessChartData(array $byDate): array
    {
        if (empty($byDate)) {
            return [];
        }

        // Get last 7 days for chart
        $last7Days = array_slice($byDate, -7, 7, true);
        
        // In a real scenario, you'd want success counts per day
        return array_values($last7Days);
    }

    /**
     * Get error chart data (mock - would need separate API endpoint)
     */
    protected function getErrorChartData(array $byDate): array
    {
        if (empty($byDate)) {
            return [];
        }

        // Get last 7 days for chart
        $last7Days = array_slice($byDate, -7, 7, true);
        
        // In a real scenario, you'd want error counts per day
        return array_map(fn($val) => max(0, $val * 0.1), array_values($last7Days));
    }
}

