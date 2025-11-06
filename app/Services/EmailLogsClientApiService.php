<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

/**
 * Email Logs API Service - Client API
 * 
 * Handles all communication with the Email Logs API for Client source.
 */
class EmailLogsClientApiService
{
    /**
     * Base URL for the Email Logs API
     */
    protected string $baseUrl;

    /**
     * Bearer token for authentication
     */
    protected ?string $token;

    /**
     * Create a new service instance.
     */
    public function __construct()
    {
        $this->baseUrl = config('services.email_logs_api.client.base_url');
        $this->token = config('services.email_logs_api.client.token');
    }

    /**
     * Get HTTP client with authentication headers.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function client()
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        return Http::withHeaders($headers)->timeout(10); // Reduced from 30s to 10s for faster failures
    }

    /**
     * Get paginated list of email logs.
     *
     * @param array $params Query parameters for filtering
     * @return array
     */
    public function getEmailLogs(array $params = []): array
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/admin/email-logs', $params);

            if ($response->successful()) {
                $data = $response->json();
                
                // Convert API data to EmailLog models
                $emailLogs = collect($data['data'] ?? [])
                    ->map(fn($item) => EmailLog::fromApi($item))
                    ->all();

                return [
                    'data' => $emailLogs,
                    'meta' => $data['meta'] ?? [],
                ];
            }

            Log::error('Failed to fetch email logs (Client)', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'data' => [],
                'meta' => [],
            ];
        } catch (RequestException $e) {
            Log::error('Email Logs API request failed (Client)', [
                'message' => $e->getMessage(),
                'params' => $params,
            ]);

            return [
                'data' => [],
                'meta' => [],
            ];
        }
    }

    /**
     * Get email log statistics.
     *
     * @param array $params Query parameters (date_from, date_to)
     * @return array
     */
    public function getStatistics(array $params = []): array
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/admin/email-logs/statistics', $params);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Failed to fetch email logs statistics (Client)', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (RequestException $e) {
            Log::error('Email Logs API statistics request failed (Client)', [
                'message' => $e->getMessage(),
                'params' => $params,
            ]);

            return [];
        }
    }

    /**
     * Get a single email log by ID.
     *
     * @param string $id Email log ID
     * @return EmailLog|null
     */
    public function getEmailLog(string $id): ?EmailLog
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/admin/email-logs/' . $id);

            if ($response->successful()) {
                $data = $response->json();
                return EmailLog::fromApi($data['data'] ?? []);
            }

            Log::error('Failed to fetch email log (Client)', [
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (RequestException $e) {
            Log::error('Email Logs API get single request failed (Client)', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return null;
        }
    }

    /**
     * Delete an email log.
     *
     * @param string $id Email log ID
     * @return bool
     */
    public function deleteEmailLog(string $id): bool
    {
        try {
            $response = $this->client()
                ->delete($this->baseUrl . '/admin/email-logs/' . $id);

            if ($response->successful()) {
                return true;
            }

            Log::error('Failed to delete email log (Client)', [
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (RequestException $e) {
            Log::error('Email Logs API delete request failed (Client)', [
                'message' => $e->getMessage(),
                'id' => $id,
            ]);

            return false;
        }
    }

    /**
     * Build query parameters for API request.
     *
     * @param array $filters
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function buildQueryParams(array $filters = [], int $perPage = 15, int $page = 1): array
    {
        $params = [
            'per_page' => $perPage,
            'page' => $page,
        ];

        // Add filters if provided
        if (!empty($filters['status'])) {
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['email_type'])) {
            $params['email_type'] = $filters['email_type'];
        }

        if (!empty($filters['recipient_email'])) {
            $params['recipient_email'] = $filters['recipient_email'];
        }

        if (!empty($filters['sender_email'])) {
            $params['sender_email'] = $filters['sender_email'];
        }

        if (!empty($filters['date_from'])) {
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $params['search'] = $filters['search'];
        }

        return $params;
    }

    /**
     * Test API connection.
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/admin/email-logs', ['per_page' => 1]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Email Logs API connection test failed (Client)', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

