<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Models\WorkelUser;
use App\Repositories\AdminRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncWorkelUsers extends Command
{
    protected $signature = 'sync:workel-users 
                            {--force : Force sync even if recently synced}
                            {--email= : Admin email for authentication}
                            {--password= : Admin password for authentication}';
    protected $description = 'Sync Workel users from Admin API to local database';

    public function __construct(
        private AdminRepository $adminRepository,
        private \App\Services\AdminApiService $adminApiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Workel users synchronization from Admin API...');

        // Check if we have a valid token (from cache, which works in CLI)
        $token = \Illuminate\Support\Facades\Cache::get('admin_api_token');
        
        // If no token and credentials provided, authenticate
        if (!$token) {
            $email = $this->option('email') ?: env('ADMIN_SYNC_EMAIL');
            $password = $this->option('password') ?: env('ADMIN_SYNC_PASSWORD');
            
            if ($email && $password) {
                $this->info('Authenticating with provided credentials...');
                $authResponse = $this->adminApiService->login($email, $password, 'CLI Sync Command');
                
                if (!$authResponse->isSuccess() || !$authResponse->getToken()) {
                    $this->error('Authentication failed: ' . ($authResponse->message ?? 'Invalid credentials'));
                    return 1;
                }
                
                $this->info('Authentication successful. Proceeding with sync...');
                $token = $authResponse->getToken();
            } else {
                $this->error('No authentication token found.');
                $this->warn('Options:');
                $this->line('  1. Log in through the web interface first (token will be cached)');
                $this->line('  2. Use --email and --password options');
                $this->line('  3. Set ADMIN_SYNC_EMAIL and ADMIN_SYNC_PASSWORD in .env file');
                return 1;
            }
        } else {
            $this->info('Using cached authentication token. Proceeding with sync...');
        }

        try {
            $page = 1;
            $perPage = 50;
            $totalSynced = 0;
            $totalUpdated = 0;
            $totalSkipped = 0;
            $hasMore = true;

            while ($hasMore) {
                $this->info("Fetching users page {$page}...");

                $response = $this->adminRepository->getUsers([
                    'page' => $page,
                    'per_page' => $perPage,
                    '_no_cache' => true, // Disable cache for sync
                ]);

                // Debug: Log the response structure
                if ($this->option('verbose')) {
                    $this->line('Response structure: ' . json_encode($response, JSON_PRETTY_PRINT));
                }

                $users = $response['data'] ?? [];
                $meta = $response['meta'] ?? [];

                // Check if response data is directly an array (not nested)
                if (empty($users) && isset($response['data']) && is_array($response['data'])) {
                    $users = $response['data'];
                }

                // If still empty, check if data is at root level
                if (empty($users) && isset($response[0])) {
                    $users = $response;
                    $meta = [];
                }

                if (empty($users)) {
                    $this->warn("No users found on page {$page}.");
                    $this->line("Response keys: " . implode(', ', array_keys($response)));
                    if (isset($response['data'])) {
                        $this->line("Data type: " . gettype($response['data']));
                        if (is_array($response['data'])) {
                            $this->line("Data count: " . count($response['data']));
                        }
                    }
                    $hasMore = false;
                    break;
                }

                $this->info("Found " . count($users) . " users on page {$page}.");

                foreach ($users as $userData) {
                    $result = $this->syncUser($userData);
                    if ($result['skipped'] ?? false) {
                        $totalSkipped++;
                    } elseif ($result['created']) {
                        $totalSynced++;
                    } else {
                        $totalUpdated++;
                    }
                }

                // Check if there are more pages
                $currentPage = $meta['current_page'] ?? $page;
                $lastPage = $meta['last_page'] ?? 1;
                $hasMore = $currentPage < $lastPage;
                $page++;

                $this->info("Processed page {$currentPage} of {$lastPage}.");
            }

            $this->info("Synchronization completed!");
            $this->info("Synced {$totalSynced} new users, updated {$totalUpdated} existing users, and skipped {$totalSkipped} duplicate emails.");
            
            Log::info('Workel users sync completed', [
                'synced' => $totalSynced,
                'updated' => $totalUpdated,
                'skipped' => $totalSkipped,
            ]);

            return 0;
        } catch (ApiException $e) {
            $this->error("Failed to sync users from Admin API: " . $e->getMessage());
            Log::error("Failed to sync users from Admin API", [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
            ]);
            return 1;
        } catch (\Exception $e) {
            $this->error("Unexpected error syncing users: " . $e->getMessage());
            Log::error("Unexpected error syncing users", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    protected function syncUser(array $userData): array
    {
        $externalId = $userData['id'] ?? null;
        $email = $userData['email'] ?? null;
        
        if (!$externalId) {
            $this->warn("Skipping user without ID: " . ($email ?? 'unknown'));
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }

        if (!$email) {
            $this->warn("Skipping user without email: " . ($externalId ?? 'unknown'));
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }

        // Check if user with same email already exists (regardless of api_type)
        $existingUser = WorkelUser::where('email', $email)->first();
        
        if ($existingUser) {
            // User with same email exists, skip creation
            $this->line("Skipping user {$email} - already exists with ID {$existingUser->id}");
            return ['created' => false, 'updated' => false, 'skipped' => true];
        }

        // Map Admin API user data to WorkelUser model
        $workelUser = WorkelUser::updateOrCreate(
            [
                'external_id' => (string) $externalId,
                'api_type' => 'Client', // Mark as from Client API
            ],
            [
                'name' => $userData['name'] ?? '',
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)), // Random password since we don't have it from API
                'phone' => $userData['phone'] ?? null,
                'address' => $userData['address'] ?? null,
                'role' => $this->mapSystemRoleToRole($userData['system_role'] ?? 'user'),
                'status' => $this->mapUserStatus($userData),
                'source' => 'admin_api',
                'email_verified_at' => isset($userData['email_verified_at']) 
                    ? \Carbon\Carbon::parse($userData['email_verified_at']) 
                    : null,
                'created_at' => isset($userData['created_at']) 
                    ? \Carbon\Carbon::parse($userData['created_at']) 
                    : now(),
                'updated_at' => isset($userData['updated_at']) 
                    ? \Carbon\Carbon::parse($userData['updated_at']) 
                    : now(),
            ]
        );

        return [
            'created' => $workelUser->wasRecentlyCreated,
            'updated' => !$workelUser->wasRecentlyCreated,
            'skipped' => false,
        ];
    }

    protected function mapSystemRoleToRole(?string $systemRole): string
    {
        return match($systemRole) {
            'admin', 'super_admin' => 'admin',
            default => 'user',
        };
    }

    protected function mapUserStatus(array $userData): string
    {
        // Check if user is suspended
        if (isset($userData['suspended_at']) && $userData['suspended_at']) {
            return 'suspended';
        }

        // Check if user is active
        if (isset($userData['status'])) {
            return match($userData['status']) {
                'active' => 'active',
                'inactive', 'suspended' => 'inactive',
                default => 'active',
            };
        }

        return 'active';
    }
}
