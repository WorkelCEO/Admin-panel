<?php

namespace App\Filament\Resources\EmailLogResource\Pages;

use App\Exceptions\ApiException;
use App\Filament\Concerns\ExtractsTableFilters;
use App\Filament\Resources\EmailLogResource;
use App\Repositories\EmailLogRepository;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class ListEmailLogs extends ListRecords
{
    use ExtractsTableFilters;

    protected static string $resource = EmailLogResource::class;

    protected EmailLogRepository $repository;
    
    protected static ?string $recordTitleAttribute = 'id';

    public function boot(EmailLogRepository $repository): void
    {
        $this->repository = $repository;
    }
    
    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 10;
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
     * Override the table query to fetch data from API
     */
    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        try {
        $perPage = $this->getTableRecordsPerPage();
        $page = request()->get('page', 1);
        
            // Extract filters using shared trait
            $filterData = $this->extractTableFilters();
            $filterData = $this->applyTabFilters($filterData);

        // Build query parameters
            $params = $this->repository->buildQueryParams('app', $filterData, $perPage, $page);

        // Fetch data from API
            $response = $this->repository->getEmailLogs('app', $params);

        // Create a custom paginator using API pagination metadata
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
            // Show user-friendly error notification
            Notification::make()
                ->title('Failed to load email logs')
                ->body('Unable to connect to the API. Please try again or contact support if the problem persists.')
                ->danger()
                ->actions([
                    Notification::make('retry')
                        ->label('Retry')
                        ->action(fn() => $this->refreshTable()),
                ])
                ->send();

            // Return empty paginator
            return new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                10,
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
            'all' => Tab::make('All Emails'),
            'success' => Tab::make('Successful'),
            'error' => Tab::make('Failed'),
            'today' => Tab::make('Today'),
        ];
    }
}

