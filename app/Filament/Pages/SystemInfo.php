<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiException;
use App\Repositories\AdminRepository;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class SystemInfo extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-information-circle';

    protected string $view = 'filament.pages.system-info';

    protected static ?string $navigationLabel = 'System Information';

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 30;

    protected AdminRepository $repository;

    public ?array $systemInfo = null;

    public function boot(AdminRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function mount(): void
    {
        $this->loadSystemInfo();
    }

    public function loadSystemInfo(): void
    {
        try {
            $info = $this->repository->getSystemInfo();
            
            if ($info) {
                $this->systemInfo = [
                    'laravel_version' => $info->getLaravelVersion(),
                    'php_version' => $info->getPhpVersion(),
                    'environment' => $info->getEnvironment(),
                    'debug_mode' => $info->isDebugMode(),
                    'timezone' => $info->getTimezone(),
                    'database_connection' => $info->getDatabaseConnection(),
                    'cache_driver' => $info->getCacheDriver(),
                    'queue_connection' => $info->getQueueConnection(),
                    'mail_driver' => $info->getMailDriver(),
                    'storage_disk' => $info->getStorageDisk(),
                ];
            }
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load system information')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->send();
        }
    }
}
