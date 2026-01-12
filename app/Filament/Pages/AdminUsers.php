<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiException;
use App\Services\AdminUserManagementService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AdminUsers extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected string $view = 'filament.pages.admin-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'user-management';

    public static function getSlug(\Filament\Panel $panel = null): string
    {
        return static::$slug ?? 'user-management';
    }

    public array $users = [];

    public array $meta = [];

    public string $search = '';

    public ?string $role = null;

    public ?string $status = null;

    public int $perPage = 10;

    public int $currentPage = 1;

    public bool $isLoading = false;

    public ?string $errorMessage = null;

    protected AdminUserManagementService $service;

    public function boot(AdminUserManagementService $service): void
    {
        $this->service = $service;
    }

    public function mount(): void
    {
        $this->loadUsers();
    }

    public function loadUsers(): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $params = $this->getFilters();

            $response = $this->service->getUsers($params);

            $this->users = $response['data'] ?? [];
            $this->meta = $response['meta'] ?? [
                'current_page' => 1,
                'per_page' => $this->perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
            ];

            // Update current page from API response
            $this->currentPage = $this->meta['current_page'] ?? 1;
        } catch (ApiException $e) {
            $this->errorMessage = $e->getMessage();
            $this->users = [];
            $this->meta = [
                'current_page' => 1,
                'per_page' => $this->perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
            ];

            Notification::make()
                ->title('Failed to load users')
                ->body('Unable to fetch users from the API. Please try again.')
                ->danger()
                ->send();
        } finally {
            $this->isLoading = false;
        }
    }

    public function updatedSearch(): void
    {
        $this->currentPage = 1;
        $this->loadUsers();
    }

    public function updatedRole(): void
    {
        $this->currentPage = 1;
        $this->loadUsers();
    }

    public function updatedStatus(): void
    {
        $this->currentPage = 1;
        $this->loadUsers();
    }

    public function updatedPerPage(): void
    {
        $this->currentPage = 1;
        $this->loadUsers();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->role = null;
        $this->status = null;
        $this->currentPage = 1;
        $this->loadUsers();
    }

    public function goToPage(int $page): void
    {
        if ($page >= 1 && $page <= ($this->meta['last_page'] ?? 1)) {
            $this->currentPage = $page;
            $this->loadUsers();
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

        if (!empty($this->role)) {
            $params['role'] = $this->role;
        }

        if (!empty($this->status)) {
            $params['status'] = $this->status;
        }

        return $params;
    }

    public function getRoleOptions(): array
    {
        return [
            'admin' => 'Admin',
            'user' => 'User',
            'pm' => 'Project Manager',
            'member' => 'Member',
        ];
    }

    public function getStatusOptions(): array
    {
        return [
            'online' => 'Online',
            'offline' => 'Offline',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }
}
