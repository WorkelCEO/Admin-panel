<?php

namespace App\Filament\Widgets;

use App\Services\EmailLogsAppApiService;
use Filament\Widgets\ChartWidget;

class EmailTypeDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Email Types Distribution - App API (Last 30 Days)';

    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $apiService = app(EmailLogsAppApiService::class);
        
        // Get statistics for last 30 days
        $stats = $apiService->getStatistics([
            'date_from' => now()->subDays(30)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        if (empty($stats) || empty($stats['by_type'])) {
            return [
                'datasets' => [
                    [
                        'label' => 'Email Types',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $byType = $stats['by_type'];

        $labels = array_map(function ($type) {
            return match ($type) {
                'verification' => 'Verification',
                'project_invitation' => 'Project Invitation',
                'workspace_invitation' => 'Workspace Invitation',
                'workspace_invitation_by_email' => 'Workspace Invitation (Email)',
                'forgot_password' => 'Password Reset',
                'unread_chat' => 'Unread Chat',
                'user_registration_notification' => 'Registration',
                default => str_replace('_', ' ', ucfirst($type)),
            };
        }, array_keys($byType));

        return [
            'datasets' => [
                [
                    'label' => 'Email Count',
                    'data' => array_values($byType),
                    'backgroundColor' => [
                        'rgb(59, 130, 246)',   // blue
                        'rgb(16, 185, 129)',   // green
                        'rgb(139, 92, 246)',   // purple
                        'rgb(245, 158, 11)',   // yellow
                        'rgb(239, 68, 68)',    // red
                        'rgb(236, 72, 153)',   // pink
                        'rgb(20, 184, 166)',   // teal
                    ],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}

