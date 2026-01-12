<?php

namespace App\Services;

use App\DTOs\ApiResponse;
use App\DTOs\BackupResponse;
use App\Exceptions\ApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * Admin Backup Service
 */
class AdminBackupService extends BaseApiService
{
    protected function getBaseUrl(): string
    {
        return config('services.admin_api.base_url', env('ADMIN_API_BASE_URL', 'https://your-domain.com/api/admin'));
    }

    protected function getToken(): ?string
    {
        return Session::get('admin_api_token') ?? Cache::get('admin_api_token');
    }

    protected function getServiceName(): string
    {
        return 'admin_api_backups';
    }

    /**
     * Get all backups
     */
    public function getBackups(): array
    {
        try {
            $response = $this->get('/backups', [], 60); // Cache for 60 seconds
            
            return $response->data ?? [];
        } catch (ApiException $e) {
            return [];
        }
    }

    /**
     * Create backup
     */
    public function createBackup(?string $description = null, bool $includeFiles = false): ?BackupResponse
    {
        try {
            $data = [];
            if ($description) {
                $data['description'] = $description;
            }
            if ($includeFiles) {
                $data['include_files'] = $includeFiles;
            }

            $response = $this->post('/backups/create', $data);
            
            return BackupResponse::fromApiResponse([
                'data' => $response->data,
                'success' => $response->success,
                'message' => $response->message,
            ]);
        } catch (ApiException $e) {
            return null;
        }
    }

    /**
     * Download backup
     */
    public function downloadBackup(string $backupId): ApiResponse
    {
        try {
            return $this->post("/backups/{$backupId}/download", []);
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Import backup
     */
    public function importBackup(string $backupId, bool $confirm = false, bool $restoreFiles = false): ApiResponse
    {
        try {
            $data = [
                'confirm' => $confirm,
                'restore_files' => $restoreFiles,
            ];

            return $this->post("/backups/{$backupId}/import", $data);
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Delete backup
     */
    public function deleteBackup(string $backupId): bool
    {
        try {
            return $this->delete("/backups/{$backupId}");
        } catch (ApiException $e) {
            return false;
        }
    }

    /**
     * Cleanup email logs
     */
    public function cleanupEmailLogs(string $olderThan = '30 days'): ApiResponse
    {
        try {
            return $this->post('/email-logs/cleanup', ['older_than' => $olderThan]);
        } catch (ApiException $e) {
            return ApiResponse::failure($e->getMessage());
        }
    }

    /**
     * Override client to include admin token
     */
    protected function client(): PendingRequest
    {
        $client = parent::client();
        
        $token = $this->getToken();
        if ($token) {
            $client->withToken($token);
        }

        return $client;
    }

    /**
     * Handle unauthorized (401) response by logging out the user
     */
    protected function handleUnauthorized(string $endpoint, array $responseData = []): bool
    {
        // Don't logout on auth endpoints
        if (str_contains($endpoint, '/auth/login') || str_contains($endpoint, '/auth/refresh')) {
            return false;
        }

        \Illuminate\Support\Facades\Log::warning("API Unauthorized: Logging out user due to 401 error", [
            'service' => $this->getServiceName(),
            'endpoint' => $endpoint,
        ]);

        $adminApiService = app(AdminApiService::class);
        $adminApiService->logoutUser();

        return false;
    }
}
