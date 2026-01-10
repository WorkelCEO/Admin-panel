<?php

namespace App\Filament\Resources\LoginHistoryResource\Pages;

use App\Filament\Resources\LoginHistoryResource;
use App\Repositories\AdminRepository;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListLoginHistory extends ListRecords
{
    protected static string $resource = LoginHistoryResource::class;

    public array $filterData = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('statistics')
                ->label('Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->action(function () {
                    $this->showStatistics();
                }),
            Actions\Action::make('cleanup')
                ->label('Cleanup Old Records')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Forms\Components\TextInput::make('older_than')
                        ->label('Delete records older than (e.g., 30 days, 3 months)')
                        ->placeholder('30 days')
                        ->default('30 days')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->cleanupLoginHistory($data['older_than']);
                }),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        return \App\Models\User::query()->whereRaw('1 = 0'); // Return a dummy query
    }

    protected function paginateTableQuery(Builder $query): Paginator|\Illuminate\Contracts\Pagination\CursorPaginator
    {
        $adminRepository = app(AdminRepository::class);

        // Get filter parameters
        $page = request()->get('page', 1);
        $perPage = request()->get('per_page', 15);
        $loggedInFrom = request()->get('tableFilters.date_range.logged_in_from');
        $loggedInTo = request()->get('tableFilters.date_range.logged_in_to');
        $isActive = request()->get('tableFilters.is_active.value');

        $params = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($loggedInFrom) {
            $params['logged_in_from'] = $loggedInFrom;
        }

        if ($loggedInTo) {
            $params['logged_in_to'] = $loggedInTo;
        }

        if ($isActive !== null) {
            $params['is_active'] = $isActive;
        }

        try {
            // Check if token exists before making API call
            $token = $adminRepository->getStoredToken();
            if (!$token) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Please log out and log in again to refresh your authentication token.')
                    ->warning()
                    ->persistent()
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

            $response = $adminRepository->getLoginHistory($params);

            // Ensure we have proper meta structure from standardized pagination
            $data = $response['data'] ?? [];
            $meta = $response['meta'] ?? [];

            // Create model instances from API data for Filament compatibility
            // Filament requires Eloquent models, not stdClass objects
            // Map API response structure to expected table columns
            $self = $this; // Capture $this for use in closure
            $items = collect($data)->map(function ($item, $index) use ($self) {
                // Convert array/stdClass to array first
                $itemArray = is_object($item) ? (array) $item : $item;
                
                // Map API fields to table columns
                // API returns: id, user_id, created_at, user.email, user_agent, ip_address, action, successful, etc.
                // Table expects: id, user_id, user_email, logged_in_at, logged_out_at, device_name, location, is_active, etc.
                
                // Extract user email from nested user object
                $userEmail = null;
                if (isset($itemArray['user']) && is_array($itemArray['user'])) {
                    $userEmail = $itemArray['user']['email'] ?? null;
                } elseif (isset($itemArray['user_email'])) {
                    $userEmail = $itemArray['user_email'];
                }
                
                $mappedData = [
                    'id' => $itemArray['id'] ?? md5(json_encode($itemArray) . $index),
                    'user_id' => $itemArray['user_id'] ?? null,
                    'user_email' => $userEmail,
                    'ip_address' => $itemArray['ip_address'] ?? null,
                    'user_agent' => $itemArray['user_agent'] ?? null,
                    'logged_in_at' => $itemArray['created_at'] ?? null, // API uses 'created_at' not 'logged_in_at'
                    'logged_out_at' => null, // API doesn't provide logged_out_at
                    'device_name' => $self->extractDeviceName($itemArray['user_agent'] ?? null),
                    'location' => null, // API doesn't provide location
                    'is_active' => $itemArray['successful'] ?? false, // Use successful flag as active indicator
                    'action' => $itemArray['action'] ?? null,
                    'successful' => $itemArray['successful'] ?? false,
                    'failure_reason' => $itemArray['failure_reason'] ?? null,
                    'api_version' => $itemArray['api_version'] ?? null,
                ];
                
                // Clean up date fields - ensure null values are preserved, never use 'N/A' as a value
                // Only null or valid date strings should be stored
                if (empty($mappedData['logged_in_at']) || 
                    $mappedData['logged_in_at'] === 'N/A' || 
                    $mappedData['logged_in_at'] === '' ||
                    !is_string($mappedData['logged_in_at']) ||
                    !preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$mappedData['logged_in_at'])) {
                    $mappedData['logged_in_at'] = null;
                } else {
                    // Ensure it's a valid date string
                    try {
                        // Validate it's a parseable date
                        \Carbon\Carbon::parse($mappedData['logged_in_at']);
                        $mappedData['logged_in_at'] = (string) $mappedData['logged_in_at'];
                    } catch (\Exception $e) {
                        // If parsing fails, set to null
                        $mappedData['logged_in_at'] = null;
                    }
                }
                
                if (empty($mappedData['logged_out_at']) || 
                    $mappedData['logged_out_at'] === 'N/A' || 
                    $mappedData['logged_out_at'] === '' ||
                    !is_string($mappedData['logged_out_at']) ||
                    !preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$mappedData['logged_out_at'])) {
                    $mappedData['logged_out_at'] = null;
                } else {
                    // Ensure it's a valid date string
                    try {
                        // Validate it's a parseable date
                        \Carbon\Carbon::parse($mappedData['logged_out_at']);
                        $mappedData['logged_out_at'] = (string) $mappedData['logged_out_at'];
                    } catch (\Exception $e) {
                        // If parsing fails, set to null
                        $mappedData['logged_out_at'] = null;
                    }
                }
                
                // Preserve original API data for reference
                $mappedData['_original'] = $itemArray;
                
                // Ensure date fields in mappedData are never 'N/A' - only null or valid date strings
                // This prevents Carbon from trying to parse 'N/A' later
                if (isset($mappedData['logged_in_at']) && ($mappedData['logged_in_at'] === 'N/A' || $mappedData['logged_in_at'] === '')) {
                    $mappedData['logged_in_at'] = null;
                }
                if (isset($mappedData['logged_out_at']) && ($mappedData['logged_out_at'] === 'N/A' || $mappedData['logged_out_at'] === '')) {
                    $mappedData['logged_out_at'] = null;
                }
                
                // Create a model-like instance
                // Use an anonymous class that extends Model to satisfy Filament's type requirements
                $model = new class($mappedData) extends \Illuminate\Database\Eloquent\Model {
                    protected $guarded = [];
                    public $timestamps = false;
                    public $incrementing = false;
                    protected $primaryKey = 'id';
                    
                    // Don't cast dates automatically - handle them manually to prevent Carbon parsing errors
                    protected $casts = [];
                    
                    public function __construct(array $attributes = [])
                    {
                        parent::__construct();
                        
                        // Clean date fields before filling to ensure no 'N/A' values
                        if (isset($attributes['logged_in_at']) && ($attributes['logged_in_at'] === 'N/A' || $attributes['logged_in_at'] === '')) {
                            $attributes['logged_in_at'] = null;
                        }
                        if (isset($attributes['logged_out_at']) && ($attributes['logged_out_at'] === 'N/A' || $attributes['logged_out_at'] === '')) {
                            $attributes['logged_out_at'] = null;
                        }
                        
                        $this->fill($attributes);
                        
                        // Ensure ID is set
                        if (isset($attributes['id'])) {
                            $this->setAttribute('id', $attributes['id']);
                        }
                    }
                    
                    public function getRouteKeyName(): string
                    {
                        return 'id';
                    }
                    
                    // Override setAttribute to prevent 'N/A' from being stored in date fields
                    public function setAttribute($key, $value)
                    {
                        // Never allow 'N/A' to be stored in date fields
                        if (in_array($key, ['logged_in_at', 'logged_out_at'])) {
                            if (empty($value) || $value === 'N/A' || $value === '') {
                                $value = null;
                            }
                        }
                        
                        return parent::setAttribute($key, $value);
                    }
                    
                    // Override date accessors to handle null values properly and prevent Carbon parsing errors
                    public function getAttribute($key)
                    {
                        // Handle date fields first - ensure they're never 'N/A'
                        if (in_array($key, ['logged_in_at', 'logged_out_at'])) {
                            $value = $this->attributes[$key] ?? null;
                            
                            // Always return null for empty, 'N/A', or invalid values
                            if (empty($value) || $value === 'N/A' || $value === '' || $value === false) {
                                return null;
                            }
                            
                            // Return as string - Filament will parse it when needed
                            return is_string($value) ? $value : (string) $value;
                        }
                        
                        // Don't return internal _original field
                        if ($key === '_original') {
                            return null;
                        }
                        
                        return parent::getAttribute($key);
                    }
                    
                    // Override to prevent automatic date casting
                    protected function castAttribute($key, $value)
                    {
                        // Never cast date fields automatically - return as-is
                        if (in_array($key, ['logged_in_at', 'logged_out_at'])) {
                            // If it's null, empty, or 'N/A', return null
                            if (empty($value) || $value === 'N/A' || $value === '') {
                                return null;
                            }
                            // Return as string - don't let Eloquent auto-cast to Carbon
                            return is_string($value) ? $value : (string) $value;
                        }
                        
                        return parent::castAttribute($key, $value);
                    }
                };
                
                return $model;
            });

            return new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $meta['total'] ?? 0,
                $meta['per_page'] ?? $perPage,
                $meta['current_page'] ?? $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        } catch (\App\Exceptions\ApiException $e) {
            // Handle API exceptions, especially 401 Unauthenticated
            $context = $e->getContext();
            $isUnauthenticated = $e->getCode() === 401 || isset($context['requires_auth']) || isset($context['status']) && $context['status'] === 401;

            if ($isUnauthenticated) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Your session has expired. Please log out and log in again to continue.')
                    ->warning()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('login')
                            ->label('Go to Login')
                            ->url(route('filament.admin.auth.login'))
                            ->button(),
                    ])
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to fetch login history')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }

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
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to fetch login history')
                ->body($e->getMessage())
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

    protected function showStatistics(): void
    {
        $adminRepository = app(AdminRepository::class);

        try {
            // Check if token exists before making API call
            $token = $adminRepository->getStoredToken();
            if (!$token) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Please log out and log in again to refresh your authentication token.')
                    ->warning()
                    ->persistent()
                    ->send();
                return;
            }

            $stats = $adminRepository->getLoginStatistics();

            $message = "**Login History Statistics**\n\n";
            $message .= "Total Logins: " . ($stats['total_logins'] ?? 0) . "\n";
            $message .= "Unique Users: " . ($stats['unique_users'] ?? 0) . "\n";
            $message .= "Today's Logins: " . ($stats['today_logins'] ?? 0) . "\n";
            $message .= "This Week's Logins: " . ($stats['this_week_logins'] ?? 0) . "\n";
            $message .= "This Month's Logins: " . ($stats['this_month_logins'] ?? 0);

            Notification::make()
                ->title('Login History Statistics')
                ->body($message)
                ->success()
                ->persistent()
                ->send();
        } catch (\App\Exceptions\ApiException $e) {
            // Handle API exceptions, especially 401 Unauthenticated
            $context = $e->getContext();
            $isUnauthenticated = $e->getCode() === 401 || isset($context['requires_auth']) || isset($context['status']) && $context['status'] === 401;

            if ($isUnauthenticated) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Your session has expired. Please log out and log in again to continue.')
                    ->warning()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('login')
                            ->label('Go to Login')
                            ->url(route('filament.admin.auth.login'))
                            ->button(),
                    ])
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to fetch statistics')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to fetch statistics')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Extract device name from user agent string
     */
    protected function extractDeviceName(?string $userAgent): ?string
    {
        if (empty($userAgent)) {
            return null;
        }
        
        // Simple device extraction from user agent
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

    protected function cleanupLoginHistory(string $olderThan): void
    {
        $adminRepository = app(AdminRepository::class);

        try {
            // Check if token exists before making API call
            $token = $adminRepository->getStoredToken();
            if (!$token) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Please log out and log in again to refresh your authentication token.')
                    ->warning()
                    ->persistent()
                    ->send();
                return;
            }

            $response = $adminRepository->cleanupLoginHistory($olderThan);

            if ($response->isSuccess()) {
                Notification::make()
                    ->title('Cleanup completed successfully')
                    ->body($response->message ?? 'Old login history records have been deleted.')
                    ->success()
                    ->send();

                $this->resetTable();
            } else {
                Notification::make()
                    ->title('Cleanup failed')
                    ->body($response->message ?? 'Please try again.')
                    ->danger()
                    ->send();
            }
        } catch (\App\Exceptions\ApiException $e) {
            // Handle API exceptions, especially 401 Unauthenticated
            $context = $e->getContext();
            $isUnauthenticated = $e->getCode() === 401 || isset($context['requires_auth']) || isset($context['status']) && $context['status'] === 401;

            if ($isUnauthenticated) {
                Notification::make()
                    ->title('Authentication Required')
                    ->body('Your session has expired. Please log out and log in again to continue.')
                    ->warning()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('login')
                            ->label('Go to Login')
                            ->url(route('filament.admin.auth.login'))
                            ->button(),
                    ])
                    ->send();
            } else {
                Notification::make()
                    ->title('Failed to cleanup login history')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Failed to cleanup login history')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}