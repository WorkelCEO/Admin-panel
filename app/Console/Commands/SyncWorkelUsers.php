<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\WorkelUser;

class SyncWorkelUsers extends Command
{
    protected $signature = 'sync:workel-users';
    protected $description = 'Sync users from external APIs';

    public function handle()
    {
        $this->syncApi('App');
        $this->syncApi('Client');

        $this->info('User synchronization completed.');
        return 0;
    }

    protected function syncApi(string $apiType)
    {
        $url = $apiType === 'App'
            ? env("APP_WORKEL_API") . '/admin/users'
            : env("CLIENT_WORKEL_API") . '/admin/users';

        $token = $apiType === 'App'
            ? env('API_APP_TOKEN')
            : env('API_CLIENT_TOKEN');

        $response = Http::withToken($token)
            ->withHeaders([
                'Accept'        => 'application/json',
                'Custom-Header' => 'CustomValue',
            ])
            ->get($url);

        if ($response->successful()) {
            $users = $response->json();
            if (empty($users)) {
                Log::info("No users found in {$apiType} API.");
            } else {
                Log::info("Users found in {$apiType} API.", ['users' => $users]);
                foreach ($users as $user) {
                    WorkelUser::updateOrCreate(
                        [
                            // Use an external_id field along with the api_type for uniqueness.
                            'external_id' => $user['id'],
                            'api_type'    => $apiType,
                        ],
                        [
                            'name'       => $user['name'],
                            'email'      => $user['email'],
                            'phone'      => $user['phone']   ?? '',
                            'address'    => $user['address'] ?? '',
                            'role'       => $user['role']    ?? 'user',
                            'password'   => $user['password'] ?? '',
                            'status'     => $user['status']  ?? 'active',
                            'source'     => $user['source']  ?? 'api',
                            'created_at' => $user['created_at'],
                            'updated_at' => $user['updated_at'],
                        ]
                    );
                }
            }
        } else {
            Log::error("Failed to fetch users from {$apiType} API", [
                'response' => $response->body()
            ]);
        }
    }
}
