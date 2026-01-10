<?php

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Exceptions\ApiException;
use App\Filament\Resources\AdminUserResource;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

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

    /**
     * Override getTableQuery to prevent Filament from using Eloquent
     */
    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        // Return a dummy query since we're using API data
        return \App\Models\User::query()->whereRaw('1 = 0');
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

            // Add search
            $search = $this->getTableSearch();
            if ($search) {
                $params['search'] = $search;
            }

            // Add filters
            $filters = $this->tableFilters ?? [];
            if (isset($filters['system_role']['value'])) {
                $params['role'] = $filters['system_role']['value'];
            }

            $response = $this->repository->getUsers($params);

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
                ->title('Failed to load users')
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
}
