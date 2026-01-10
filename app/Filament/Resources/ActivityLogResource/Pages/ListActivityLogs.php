<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Exceptions\ApiException;
use App\Filament\Resources\ActivityLogResource;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected AdminRepository $repository;

    public function boot(AdminRepository $repository): void
    {
        $this->repository = $repository;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->refreshTable();
                    Notification::make()
                        ->title('Data refreshed')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function paginateTableQuery(Builder $query): Paginator
    {
        try {
            $perPage = $this->getTableRecordsPerPage();
            $page = request()->get('page', 1);

            $params = [
                'page' => $page,
                'per_page' => $perPage,
            ];

            // Add filters
            $filters = $this->tableFilters ?? [];
            if (isset($filters['action']['value'])) {
                $params['action'] = $filters['action']['value'];
            }

            // Check if admin filter is active
            $activeTab = $this->activeTab ?? 'all';
            if ($activeTab === 'admin') {
                $response = $this->repository->getAdminActivityLogs($params);
            } else {
                $response = $this->repository->getActivityLogs($params);
            }

            // Ensure we have proper meta structure from standardized pagination
            $meta = $response['meta'] ?? [];

            return new \Illuminate\Pagination\LengthAwarePaginator(
                $response['data'] ?? [],
                $meta['total'] ?? 0,
                $meta['per_page'] ?? $perPage,
                $meta['current_page'] ?? $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load activity logs')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->send();

            return new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                15,
                1,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        }
    }

    public function getTabs(): array
    {
        return [
            'all' => \Filament\Resources\Components\Tab::make('All Logs'),
            'admin' => \Filament\Resources\Components\Tab::make('Admin Logs'),
        ];
    }
}
