<?php

namespace App\Console\Commands;

use App\Repositories\AdminRepository;
use Illuminate\Console\Command;

class TestAdminLogin extends Command
{
    protected $signature = 'admin:test-login {email} {password}';
    protected $description = 'Test admin login with provided credentials';

    public function handle(AdminRepository $repository): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $this->info("Testing admin login for: {$email}");
        $this->info("API Base URL: " . config('services.admin_api.base_url'));
        $this->newLine();

        try {
            $response = $repository->login($email, $password, 'CLI Test');

            if ($response->isSuccess()) {
                $this->info('✓ Login successful!');
                $this->info('Token: ' . substr($response->getToken() ?? 'N/A', 0, 50) . '...');
                $this->info('Token Type: ' . $response->getTokenType());
                $this->info('Expires At: ' . ($response->getExpiresAt() ?? 'N/A'));
                
                $user = $response->getUser();
                if ($user) {
                    $this->newLine();
                    $this->info('User Information:');
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['ID', $user['id'] ?? 'N/A'],
                            ['Name', $user['name'] ?? 'N/A'],
                            ['Email', $user['email'] ?? 'N/A'],
                            ['System Role', $user['system_role'] ?? 'N/A'],
                            ['Super Admin', ($user['is_super_admin'] ?? false) ? 'Yes' : 'No'],
                        ]
                    );
                }

                return 0;
            } else {
                $this->error('✗ Login failed!');
                $this->error('Message: ' . ($response->message ?? 'Unknown error'));
                $this->error('Success: ' . ($response->isSuccess() ? 'true' : 'false'));
                $this->error('Has Token: ' . (empty($response->getToken()) ? 'No' : 'Yes'));
                
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception occurred:');
            $this->error('Message: ' . $e->getMessage());
            $this->error('Class: ' . get_class($e));
            
            if ($e instanceof \App\Exceptions\ApiException) {
                $this->error('Endpoint: ' . ($e->getEndpoint() ?? 'N/A'));
                $context = $e->getContext();
                if ($context) {
                    $this->error('Status: ' . ($context['status'] ?? 'N/A'));
                    if (isset($context['body'])) {
                        $this->error('Response Body: ' . $context['body']);
                    }
                    if (isset($context['response_data'])) {
                        $this->error('Response Data: ' . json_encode($context['response_data'], JSON_PRETTY_PRINT));
                    }
                }
            }
            
            return 1;
        }
    }
}
