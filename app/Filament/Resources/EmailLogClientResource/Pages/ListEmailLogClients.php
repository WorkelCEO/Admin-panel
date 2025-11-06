<?php

namespace App\Filament\Resources\EmailLogClientResource\Pages;

use App\Filament\Resources\EmailLogClientResource;
use App\Services\EmailLogsClientApiService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Pagination\CursorPaginator;

class ListEmailLogClients extends ListRecords
{
    protected static string $resource = EmailLogClientResource::class;

    protected EmailLogsClientApiService $apiService;
    
    // Reduce default items per page for faster loading
    protected static ?string $recordTitleAttribute = 'id';

    public function boot(EmailLogsClientApiService $apiService): void
    {
        $this->apiService = $apiService;
    }
    
    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 10; // Reduced from 15 to 10 for faster initial load
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action('$refresh'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // EmailLogClientResource\Widgets\EmailLogStatsOverview::class,
        ];
    }

    /**
     * Override the table query to fetch data from API
     */
    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        $perPage = $this->getTableRecordsPerPage();
        $page = request()->get('page', 1);
        
        // Get table filters
        $filters = $this->tableFilters ?? [];
        $filterData = [];

        // Extract filter values
        if (isset($filters['status']['value'])) {
            $filterData['status'] = $filters['status']['value'];
        }

        if (isset($filters['email_type']['value'])) {
            $filterData['email_type'] = $filters['email_type']['value'];
        }

        if (isset($filters['date_range'])) {
            if (!empty($filters['date_range']['date_from'])) {
                $filterData['date_from'] = $filters['date_range']['date_from'];
            }
            if (!empty($filters['date_range']['date_to'])) {
                $filterData['date_to'] = $filters['date_range']['date_to'];
            }
        }

        if (isset($filters['recipient_email']['recipient_email'])) {
            $filterData['recipient_email'] = $filters['recipient_email']['recipient_email'];
        }

        // Get search value
        $search = $this->getTableSearch();
        if ($search) {
            $filterData['search'] = $search;
        }

        // Apply tab filters
        $activeTab = $this->activeTab ?? 'all';
        if ($activeTab === 'success') {
            $filterData['status'] = 'success';
        } elseif ($activeTab === 'error') {
            $filterData['status'] = 'error';
        } elseif ($activeTab === 'today') {
            $filterData['date_from'] = now()->format('Y-m-d');
            $filterData['date_to'] = now()->format('Y-m-d');
        }

        // Build query parameters
        $params = $this->apiService->buildQueryParams($filterData, $perPage, $page);

        // Fetch data from API
        $response = $this->apiService->getEmailLogs($params);

        // Create a custom paginator
        $meta = $response['meta'];
        
        return new \Illuminate\Pagination\LengthAwarePaginator(
            $response['data'],
            $meta['total'] ?? 0,
            $meta['per_page'] ?? $perPage,
            $meta['current_page'] ?? $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
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

