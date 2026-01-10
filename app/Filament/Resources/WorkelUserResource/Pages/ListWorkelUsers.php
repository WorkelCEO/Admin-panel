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
            
            // Get page number from Livewire pagination state
            // In Filament with Livewire, pagination is stored in component state
            // When user clicks page 2, Livewire sends: {"method":"gotoPage","params":[2,"page"]}
            // or stores it in the component snapshot as: paginators: [{page: 2}]
            $page = 1; // default
            
            // First, check Livewire calls - gotoPage method is called when user clicks pagination
            $components = request()->input('components', []);
            if (!empty($components)) {
                foreach ($components as $component) {
                    // Check calls array for gotoPage method
                    if (isset($component['calls']) && is_array($component['calls'])) {
                        foreach ($component['calls'] as $call) {
                            if (isset($call['method']) && $call['method'] === 'gotoPage' && isset($call['params'][0])) {
                                $page = (int) $call['params'][0];
                                break 2; // Break out of both loops
                            }
                        }
                    }
                    
                    // Also check snapshot for paginators state
                    if (isset($component['snapshot'])) {
                        try {
                            $snapshot = is_string($component['snapshot']) 
                                ? json_decode($component['snapshot'], true) 
                                : $component['snapshot'];
                            
                            if (isset($snapshot['data']['paginators']) && is_array($snapshot['data']['paginators'])) {
                                $paginators = $snapshot['data']['paginators'];
                                if (!empty($paginators) && isset($paginators[0]['page'])) {
                                    $page = (int) $paginators[0]['page'];
                                    break; // Found in snapshot, break outer loop
                                }
                            }
                        } catch (\Exception $e) {
                            // Continue if parsing fails
                        }
                    }
                }
            }
            
            // Fallback to URL query string if Livewire data not available (for direct URL access)
            if ($page === 1) {
                $queryString = parse_url(request()->fullUrl(), PHP_URL_QUERY);
                if ($queryString) {
                    parse_str($queryString, $queryParams);
                    if (isset($queryParams['page']) && is_numeric($queryParams['page'])) {
                        $page = (int) $queryParams['page'];
                    }
                }
            }
            
            // Final fallback to standard request methods
            if ($page === 1) {
                $pageFromRequest = request()->query('page') ?? request()->input('page') ?? request()->get('page');
                if ($pageFromRequest && is_numeric($pageFromRequest)) {
                    $page = (int) $pageFromRequest;
                }
            }
            
            // Ensure page is at least 1
            $page = max(1, $page);

            $params = [
                'page' => $page,
                'per_page' => (int) $perPage,
            ];

            // Add search
            $search = $this->getTableSearch();
            if ($search) {
                $params['search'] = $search;
            }

            // Add filters - extract from Livewire component property
            $filters = $this->tableFilters ?? [];
            if (isset($filters['api_type']['value'])) {
                $params['api_type'] = $filters['api_type']['value'];
            }
            if (isset($filters['system_role']['value'])) {
                $params['role'] = $filters['system_role']['value'];
            }
            if (isset($filters['status']['value'])) {
                $params['status'] = $filters['status']['value'];
            }

            // Filter out admin users (non-Workel users)
            $params['exclude_admin'] = true;

            // Handle tabs
            if ($this->activeTab === 'app') {
                $params['api_type'] = 'App';
            } elseif ($this->activeTab === 'client') {
                $params['api_type'] = 'Client';
            }

            // Log pagination parameters for debugging - check all possible sources
            \Illuminate\Support\Facades\Log::debug('WorkelUser pagination request', [
                'final_page' => $page,
                'per_page' => $perPage,
                'params_sent_to_api' => $params,
                'request_query_all' => request()->query(),
                'request_all' => request()->all(),
                'url_page_param' => request()->get('page'),
                'full_url' => request()->fullUrl(),
                'has_tablePage_property' => property_exists($this, 'tablePage'),
                'tablePage_value' => property_exists($this, 'tablePage') ? $this->tablePage : 'N/A',
            ]);

            $response = $this->repository->getUsers($params);

            // Ensure we have proper meta structure from standardized pagination
            $meta = $response['meta'] ?? [];
            
            // Log API response for debugging
            \Illuminate\Support\Facades\Log::debug('WorkelUser pagination response', [
                'api_current_page' => $meta['current_page'] ?? null,
                'api_total' => $meta['total'] ?? null,
                'api_per_page' => $meta['per_page'] ?? null,
                'data_count' => count($response['data'] ?? []),
                'requested_page' => $page,
            ]);
            
            // Convert API response arrays to WorkelUser Model instances for Filament compatibility
            $data = $response['data'] ?? [];
            $items = collect($data)->map(function ($item) {
                // Convert array/stdClass to array first
                $itemArray = is_object($item) ? (array) $item : $item;
                
                // Ensure ID exists - without it, navigation won't work
                if (empty($itemArray['id'] ?? null)) {
                    return null;
                }
                
                // Create WorkelUser model instance with API data
                // Use make() to create instance without saving to database
                $model = new \App\Models\WorkelUser();
                
                // Set exists to true so Filament knows this is a persisted record
                $model->exists = true;
                
                // Set primary key and fillable attributes
                $model->setAttribute('id', $itemArray['id'] ?? null);
                $model->setAttribute('name', $itemArray['name'] ?? null);
                $model->setAttribute('email', $itemArray['email'] ?? null);
                $model->setAttribute('phone', $itemArray['phone'] ?? null);
                $model->setAttribute('address', $itemArray['address'] ?? null);
                $model->setAttribute('system_role', $itemArray['system_role'] ?? 'user');
                $model->setAttribute('source', $itemArray['source'] ?? null);
                $model->setAttribute('api_type', $itemArray['api_type'] ?? null);
                $model->setAttribute('status', $itemArray['status'] ?? ($itemArray['suspended_at'] ? 'suspended' : 'active'));
                $model->setAttribute('email_verified_at', $itemArray['email_verified_at'] ?? null);
                $model->setAttribute('created_at', $itemArray['created_at'] ?? null);
                $model->setAttribute('updated_at', $itemArray['updated_at'] ?? null);
                
                // Set additional attributes that aren't fillable but come from API
                // These need to be set via setAttribute to be accessible
                $model->setAttribute('is_super_admin', $itemArray['is_super_admin'] ?? false);
                $model->setAttribute('last_seen', $itemArray['last_seen'] ?? null);
                $model->setAttribute('suspended_at', $itemArray['suspended_at'] ?? null);
                $model->setAttribute('suspension_reason', $itemArray['suspension_reason'] ?? null);
                $model->setAttribute('suspended_until', $itemArray['suspended_until'] ?? null);
                
                // Ensure ID is set in the attributes array for proper access
                if ($model->id) {
                    $model->syncOriginal();
                }
                
                return $model;
            })->filter(function ($item) {
                // Filter out null items (records without IDs)
                return $item !== null && !empty($item->getKey());
            });
            
            // Use the requested page for the paginator (what the user clicked)
            // The API's current_page should match, but we use the requested page for display
            // If API returns a different page (e.g., last page if requested page doesn't exist), 
            // we still use the requested page for the paginator to show correct UI
            $currentPage = max(1, (int) ($meta['current_page'] ?? $page));
            
            // However, if we requested a specific page and got different data, log it for debugging
            if ($page !== $currentPage && isset($meta['current_page'])) {
                \Illuminate\Support\Facades\Log::warning('Page mismatch detected', [
                    'requested_page' => $page,
                    'api_current_page' => $meta['current_page'],
                    'using_page' => $currentPage,
                ]);
            }
            
            return new \Illuminate\Pagination\LengthAwarePaginator(
                $items->values(), // Re-index the collection after filtering
                (int) ($meta['total'] ?? 0),
                (int) ($meta['per_page'] ?? $perPage),
                $currentPage, // Use API's current_page as source of truth
                [
                    'path' => request()->url(),
                    'pageName' => 'page', // Explicitly set page parameter name
                ]
            );
        } catch (ApiException $e) {
            Notification::make()
                ->title('Failed to load users')
                ->body('Unable to connect to the API. Please try again.')
                ->danger()
                ->send();

            return new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
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
                    ->badge($allCount)
                    ->badgeColor('gray'),
                'app' => Tab::make('App')
                    ->badge($appCount)
                    ->badgeColor('success'),
                'client' => Tab::make('Client')
                    ->badge($clientCount)
                    ->badgeColor('warning'),
            ];
        } catch (ApiException $e) {
            // Silently fail for tabs - don't show errors for badge counts
            // User can still use the tabs, just without counts
            return [
                'all' => Tab::make('All Users'),
                'app' => Tab::make('App'),
                'client' => Tab::make('Client'),
            ];
        } catch (\Exception $e) {
            // Fallback if any other error occurs
            return [
                'all' => Tab::make('All Users'),
                'app' => Tab::make('App'),
                'client' => Tab::make('Client'),
            ];
        }
    }
}
