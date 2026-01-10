<?php

namespace App\Filament\Resources\WorkelUserResource\Pages;

use App\Exceptions\ApiException;
use App\Filament\Resources\WorkelUserResource;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;

class ListWorkelUsers extends ListRecords
{
    protected static string $resource = WorkelUserResource::class;

    public ?string $activeTab = 'all';

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
        return \App\Models\WorkelUser::query()->whereRaw('1 = 0');
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
            if (isset($filters['api_type']['value'])) {
                $params['api_type'] = $filters['api_type']['value'];
            }
            if (isset($filters['role']['value'])) {
                $params['role'] = $filters['role']['value'];
            }

            // Filter out admin users (non-Workel users)
            $params['exclude_admin'] = true;

            // Handle tabs
            if ($this->activeTab === 'app') {
                $params['api_type'] = 'App';
            } elseif ($this->activeTab === 'client') {
                $params['api_type'] = 'Client';
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

    public function getTabs(): array
    {
        try {
            // Get counts from API for each tab
            $allParams = ['exclude_admin' => true, 'per_page' => 1];
            $allResponse = $this->repository->getUsers($allParams);
            $allCount = $allResponse['meta']['total'] ?? 0;

            $appParams = ['exclude_admin' => true, 'api_type' => 'App', 'per_page' => 1];
            $appResponse = $this->repository->getUsers($appParams);
            $appCount = $appResponse['meta']['total'] ?? 0;

            $clientParams = ['exclude_admin' => true, 'api_type' => 'Client', 'per_page' => 1];
            $clientResponse = $this->repository->getUsers($clientParams);
            $clientCount = $clientResponse['meta']['total'] ?? 0;

            return [
                'all' => Tab::make('All Users')
                    ->badge($allCount),
                'app' => Tab::make('App')
                    ->badge($appCount),
                'client' => Tab::make('Client')
                    ->badge($clientCount),
            ];
        } catch (\Exception $e) {
            // Fallback if API fails
            return [
                'all' => Tab::make('All Users')
                    ->badge(0),
                'app' => Tab::make('App')
                    ->badge(0),
                'client' => Tab::make('Client')
                    ->badge(0),
            ];
        }
    }
}
