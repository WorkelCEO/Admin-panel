<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiException;
use App\Repositories\AdminRepository;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SystemHealth extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-heart';

    protected string $view = 'filament.pages.system-health';

    protected static ?string $navigationLabel = 'System Health';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 31;

    protected AdminRepository $repository;

    public ?array $systemHealth = null;

    public function boot(AdminRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function mount(): void
    {
        $this->loadSystemHealth();
    }

    public function loadSystemHealth(): void
    {
        try {
            $health = $this->repository->getSystemHealth();
            
            if ($health) {
                $this->systemHealth = [
                    'database' => $health->getDatabase(),
                    'cache' => $health->getCache(),
                    'redis' => $health->getRedis(),
                    'storage' => $health->getStorage(),
                    'logs' => $health->getLogs(),
                    'is_healthy' => $health->isHealthy(),
                ];
            }
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load system health')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->send();
        }
    }

    public function refresh(): void
    {
        $this->loadSystemHealth();
        Notification::make()
            ->title('System health refreshed')
            ->success()
            ->send();
    }
}
