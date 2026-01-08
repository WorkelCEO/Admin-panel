<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Log;

/**
 * Workel User API Service
 * Handles user synchronization from external APIs
 */
class WorkelUserApiService extends BaseApiService
{
    private string $apiType;

    public function __construct(string $apiType)
    {
        $this->apiType = $apiType;
        parent::__construct();
    }

    protected function getBaseUrl(): string
    {
        return match ($this->apiType) {
            'App' => config('services.workel_api.app.base_url', env('APP_WORKEL_API')),
            'Client' => config('services.workel_api.client.base_url', env('CLIENT_WORKEL_API')),
            default => throw new \InvalidArgumentException("Invalid API type: {$this->apiType}"),
        };
    }

    protected function getToken(): ?string
    {
        return match ($this->apiType) {
            'App' => config('services.workel_api.app.token', env('API_APP_TOKEN')),
            'Client' => config('services.workel_api.client.token', env('API_CLIENT_TOKEN')),
            default => null,
        };
    }

    protected function getServiceName(): string
    {
        return "workel_users_{$this->apiType}";
    }

    /**
     * Get users from API
     *
     * @return array
     */
    public function getUsers(): array
    {
        try {
            $response = $this->get('/admin/users', [], 60); // Cache for 60 seconds
            
            return $response->data ?? [];
        } catch (ApiException $e) {
            Log::error("Failed to fetch users from {$this->apiType} API", [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
            ]);

            return [];
        }
    }
}
