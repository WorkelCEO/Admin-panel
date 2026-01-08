<?php

namespace App\Services;

use App\DTOs\EmailLogListResponse;
use App\DTOs\EmailLogStatisticsResponse;
use App\Exceptions\ApiException;
use App\Exceptions\ApiNotFoundException;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;

/**
 * Email Logs API Service - Client API
 * 
 * Handles all communication with the Email Logs API for Client source.
 */
class EmailLogsClientApiService extends BaseApiService
{
    protected function getBaseUrl(): string
    {
        return config('services.email_logs_api.client.base_url');
    }

    protected function getToken(): ?string
    {
        return config('services.email_logs_api.client.token');
    }

    protected function getServiceName(): string
    {
        return 'email_logs_client';
    }

    /**
     * Get paginated list of email logs.
     *
     * @param array $params Query parameters for filtering
     * @return array{data: EmailLog[], meta: array}
     */
    public function getEmailLogs(array $params = []): array
    {
        try {
            $response = $this->get('/admin/email-logs', $params, 30); // Cache for 30 seconds
            
            $emailLogListResponse = EmailLogListResponse::fromApiResponse([
                'data' => $response->data,
                'meta' => $response->meta,
            ]);

            return [
                'data' => $emailLogListResponse->getEmailLogs(),
                'meta' => [
                    'total' => $emailLogListResponse->getTotal(),
                    'per_page' => $emailLogListResponse->getPerPage(),
                    'current_page' => $emailLogListResponse->getCurrentPage(),
                    'last_page' => $emailLogListResponse->getLastPage(),
                ],
            ];
        } catch (ApiException $e) {
            Log::error('Failed to fetch email logs (Client)', [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
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
            $response = $this->get('/admin/email-logs/statistics', $params, 60); // Cache for 60 seconds
            
            $statsResponse = EmailLogStatisticsResponse::fromApiResponse([
                'data' => $response->data,
            ]);

            return [
                'total' => $statsResponse->getTotal(),
                'success' => $statsResponse->getSuccess(),
                'error' => $statsResponse->getError(),
                'success_rate' => $statsResponse->getSuccessRate(),
                'by_date' => $statsResponse->getByDate(),
            ];
        } catch (ApiException $e) {
            Log::error('Failed to fetch email logs statistics (Client)', [
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
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
            $response = $this->get("/admin/email-logs/{$id}", [], 300); // Cache for 5 minutes
            
            if (empty($response->data)) {
                return null;
            }

            return EmailLog::fromApi($response->data);
        } catch (ApiNotFoundException $e) {
            Log::warning('Email log not found (Client)', [
                'id' => $id,
                'endpoint' => $e->getEndpoint(),
            ]);
            return null;
        } catch (ApiException $e) {
            Log::error('Failed to fetch email log (Client)', [
                'id' => $id,
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
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
            return $this->delete("/admin/email-logs/{$id}");
        } catch (ApiException $e) {
            Log::error('Failed to delete email log (Client)', [
                'id' => $id,
                'message' => $e->getMessage(),
                'endpoint' => $e->getEndpoint(),
                'context' => $e->getContext(),
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

}

