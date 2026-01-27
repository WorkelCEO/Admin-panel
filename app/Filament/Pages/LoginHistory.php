<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiException;
use App\Repositories\AdminRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LoginHistory extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Login History';

    protected static ?string $title = 'Login History';

    protected static ?int $navigationSort = 2;

    protected static \UnitEnum|string|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'login-history';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.login-history';

    public array $records = [];

    public array $meta = [];

    public array $stats = [];

    public string $search = '';

    public ?string $loggedInFrom = null;

    public ?string $loggedInTo = null;

    public ?string $actionFilter = null;

    public ?string $statusFilter = null;

    public int $perPage = 15;

    public int $currentPage = 1;

    public bool $isLoading = false;

    public ?string $errorMessage = null;

    protected AdminRepository $adminRepository;

    public function boot(AdminRepository $adminRepository): void
    {
        $this->adminRepository = $adminRepository;
    }

    public function mount(): void
    {
        $this->loadLoginHistory();
        $this->loadStats();
    }

    public function loadStats(): void
    {
        try {
            $token = $this->adminRepository->getStoredToken();
            if ($token) {
                $this->stats = $this->adminRepository->getLoginStatistics();
            }
        } catch (\Throwable $e) {
            $this->stats = [];
        }
    }

    public function loadLoginHistory(): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $token = $this->adminRepository->getStoredToken();
            if (!$token) {
                $this->errorMessage = 'Authentication required. Please log out and log in again.';
                $this->records = [];
                $this->meta = $this->emptyMeta();
                $this->isLoading = false;
                return;
            }

            $params = $this->getFilters();
            $response = $this->adminRepository->getLoginHistory($params);

            $data = $response['data'] ?? [];
            $meta = $response['meta'] ?? $this->emptyMeta();

            $this->records = collect($data)->map(fn ($item, $index) => $this->mapRecord($item, $index))->values()->all();
            $this->meta = array_merge($this->emptyMeta(), $meta);
            $this->currentPage = $this->meta['current_page'] ?? 1;
        } catch (ApiException $e) {
            $this->errorMessage = $e->getMessage();
            $this->records = [];
            $this->meta = $this->emptyMeta();
            Notification::make()
                ->title('Failed to load login history')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->records = [];
            $this->meta = $this->emptyMeta();
            Notification::make()
                ->title('Failed to load login history')
                ->body('An unexpected error occurred. Please try again.')
                ->danger()
                ->send();
        } finally {
            $this->isLoading = false;
        }
    }

    protected function emptyMeta(): array
    {
        return [
            'current_page' => 1,
            'per_page' => $this->perPage,
            'total' => 0,
            'last_page' => 1,
            'from' => null,
            'to' => null,
        ];
    }

    protected function mapRecord(mixed $item, int $index): array
    {
        $arr = is_object($item) ? (array) $item : $item;
        $userEmail = null;
        if (isset($arr['user']) && is_array($arr['user'])) {
            $userEmail = $arr['user']['email'] ?? null;
        } elseif (isset($arr['user_email'])) {
            $userEmail = $arr['user_email'];
        }
        $loggedInAt = $arr['created_at'] ?? null;
        if ($loggedInAt && !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $loggedInAt)) {
            $loggedInAt = null;
        }
        return [
            'id' => $arr['id'] ?? md5(json_encode($arr) . (string) $index),
            'user_id' => $arr['user_id'] ?? null,
            'user_email' => $userEmail,
            'ip_address' => $arr['ip_address'] ?? null,
            'user_agent' => $arr['user_agent'] ?? null,
            'logged_in_at' => $loggedInAt,
            'device_name' => $this->extractDeviceName($arr['user_agent'] ?? null),
            'action' => $arr['action'] ?? null,
            'successful' => $arr['successful'] ?? false,
            'failure_reason' => $arr['failure_reason'] ?? null,
        ];
    }

    protected function extractDeviceName(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown';
        }
        if (stripos($userAgent, 'Mobile') !== false || stripos($userAgent, 'Android') !== false || stripos($userAgent, 'iPhone') !== false) {
            return 'Mobile';
        }
        if (stripos($userAgent, 'Windows') !== false) {
            return 'Windows';
        }
        if (stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'Mac OS') !== false) {
            return 'Mac';
        }
        if (stripos($userAgent, 'Linux') !== false) {
            return 'Linux';
        }
        return 'Unknown';
    }

    public function updatedSearch(): void
    {
        $this->currentPage = 1;
        $this->loadLoginHistory();
    }

    public function updatedLoggedInFrom(): void
    {
        $this->currentPage = 1;
        $this->loadLoginHistory();
    }

    public function updatedLoggedInTo(): void
    {
        $this->currentPage = 1;
        $this->loadLoginHistory();
    }

    public function updatedActionFilter(): void
    {
        $this->currentPage = 1;
        $this->loadLoginHistory();
    }

    public function updatedStatusFilter(): void
    {
        $this->currentPage = 1;
        $this->loadLoginHistory();
    }

    public function updatedPerPage(): void
    {
        $this->currentPage = 1;
        $this->loadLoginHistory();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->loggedInFrom = null;
        $this->loggedInTo = null;
        $this->actionFilter = null;
        $this->statusFilter = null;
        $this->currentPage = 1;
        $this->loadLoginHistory();
    }

    public function goToPage(int $page): void
    {
        if ($page >= 1 && $page <= ($this->meta['last_page'] ?? 1)) {
            $this->currentPage = $page;
            $this->loadLoginHistory();
        }
    }

    protected function getFilters(): array
    {
        $params = [
            'page' => $this->currentPage,
            'per_page' => $this->perPage,
        ];
        if (!empty($this->search)) {
            $params['search'] = $this->search;
        }
        if (!empty($this->loggedInFrom)) {
            $params['logged_in_from'] = $this->loggedInFrom;
        }
        if (!empty($this->loggedInTo)) {
            $params['logged_in_to'] = $this->loggedInTo;
        }
        if ($this->actionFilter !== null && $this->actionFilter !== '') {
            $params['action'] = $this->actionFilter;
        }
        if ($this->statusFilter !== null && $this->statusFilter !== '') {
            $params['is_active'] = $this->statusFilter === 'active' ? '1' : '0';
        }
        return $params;
    }

    public function getActionOptions(): array
    {
        return [
            'login' => 'Login',
            'logout' => 'Logout',
            'register' => 'Register',
        ];
    }

    public function getStatusOptions(): array
    {
        return [
            'active' => 'Successful',
            'inactive' => 'Failed',
        ];
    }

    public function showStatistics(): void
    {
        try {
            $token = $this->adminRepository->getStoredToken();
            if (!$token) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Please log out and log in again to refresh your token.')
                    ->warning()
                    ->persistent()
                    ->send();
                return;
            }
            $this->loadStats();
            $s = $this->stats;
            $body = "**Login History Statistics**\n\n";
            $body .= "Total Logins: " . ($s['total_logins'] ?? 0) . "\n";
            $body .= "Unique Users: " . ($s['unique_users'] ?? 0) . "\n";
            $body .= "Today's Logins: " . ($s['today_logins'] ?? 0) . "\n";
            $body .= "This Week: " . ($s['this_week_logins'] ?? 0) . "\n";
            $body .= "This Month: " . ($s['this_month_logins'] ?? 0);
            Notification::make()
                ->title('Login History Statistics')
                ->body($body)
                ->success()
                ->persistent()
                ->send();
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load statistics')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to load statistics')
                ->body('An unexpected error occurred.')
                ->danger()
                ->send();
        }
    }

    public function cleanupLoginHistory(?string $olderThan = null): void
    {
        try {
            $token = $this->adminRepository->getStoredToken();
            if (!$token) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Please log out and log in again.')
                    ->warning()
                    ->persistent()
                    ->send();
                return;
            }
            $response = $this->adminRepository->cleanupLoginHistory($olderThan ?? '30 days');
            if ($response->isSuccess()) {
                Notification::make()
                    ->title('Cleanup completed')
                    ->body($response->message ?? 'Old records have been deleted.')
                    ->success()
                    ->send();
                $this->loadLoginHistory();
                $this->loadStats();
            } else {
                Notification::make()
                    ->title('Cleanup failed')
                    ->body($response->message ?? 'Please try again.')
                    ->danger()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Cleanup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('statistics')
                ->label('Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->action('showStatistics'),
            Action::make('cleanup')
                ->label('Cleanup Old Records')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cleanup login history')
                ->modalDescription('Delete records older than the specified period. This cannot be undone.')
                ->form([
                    TextInput::make('older_than')
                        ->label('Delete records older than')
                        ->placeholder('30 days')
                        ->default('30 days')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->cleanupLoginHistory($data['older_than'] ?? '30 days');
                }),
        ];
    }
}
