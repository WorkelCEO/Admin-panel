<?php

namespace App\Filament\Widgets;

use App\Repositories\EmailLogRepository;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class ApiConnectionStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.api-connection-status-widget';
    
    protected int | string | array $columnSpan = 'full';

    public function mount(EmailLogRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function getViewData(): array
    {
        return [
            'appStatus' => $this->getConnectionStatus('app'),
            'clientStatus' => $this->getConnectionStatus('client'),
        ];
    }

    protected function getConnectionStatus(string $source): array
    {
        $cacheKey = "api_connection_status_{$source}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $repository = app(EmailLogRepository::class);
        $isConnected = $repository->testConnection($source);
        $status = [
            'connected' => $isConnected,
            'lastChecked' => now(),
            'status' => $isConnected ? 'success' : 'danger',
            'message' => $isConnected 
                ? 'Connected' 
                : 'Connection failed',
        ];

        // Cache for 5 minutes
        Cache::put($cacheKey, $status, 300);

        return $status;
    }

    public function testConnection(string $source): void
    {
        Cache::forget("api_connection_status_{$source}");
        $this->getConnectionStatus($source);
        
        \Filament\Notifications\Notification::make()
            ->title("Connection test completed for {$source} API")
            ->success()
            ->send();
    }
}
