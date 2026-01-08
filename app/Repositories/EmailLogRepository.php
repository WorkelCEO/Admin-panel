<?php

namespace App\Repositories;

use App\Models\EmailLog;
use App\Services\EmailLogsAppApiService;
use App\Services\EmailLogsClientApiService;

/**
 * Repository for Email Log operations
 * Abstracts API source selection and provides unified interface
 */
class EmailLogRepository
{
    public function __construct(
        private EmailLogsAppApiService $appService,
        private EmailLogsClientApiService $clientService
    ) {
    }

    /**
     * Get email logs from specified source
     *
     * @param string $source 'app' or 'client'
     * @param array $params Query parameters
     * @return array{data: EmailLog[], meta: array}
     */
    public function getEmailLogs(string $source, array $params = []): array
    {
        return $this->getService($source)->getEmailLogs($params);
    }

    /**
     * Get single email log by ID
     *
     * @param string $source 'app' or 'client'
     * @param string $id Email log ID
     * @return EmailLog|null
     */
    public function getEmailLog(string $source, string $id): ?EmailLog
    {
        return $this->getService($source)->getEmailLog($id);
    }

    /**
     * Delete email log
     *
     * @param string $source 'app' or 'client'
     * @param string $id Email log ID
     * @return bool
     */
    public function deleteEmailLog(string $source, string $id): bool
    {
        return $this->getService($source)->deleteEmailLog($id);
    }

    /**
     * Get statistics
     *
     * @param string $source 'app' or 'client'
     * @param array $params Query parameters
     * @return array
     */
    public function getStatistics(string $source, array $params = []): array
    {
        return $this->getService($source)->getStatistics($params);
    }

    /**
     * Build query parameters
     *
     * @param string $source 'app' or 'client'
     * @param array $filters Filters
     * @param int $perPage Items per page
     * @param int $page Page number
     * @return array
     */
    public function buildQueryParams(string $source, array $filters = [], int $perPage = 15, int $page = 1): array
    {
        return $this->getService($source)->buildQueryParams($filters, $perPage, $page);
    }

    /**
     * Test connection for source
     *
     * @param string $source 'app' or 'client'
     * @return bool
     */
    public function testConnection(string $source): bool
    {
        return $this->getService($source)->testConnection();
    }

    /**
     * Get service instance for source
     */
    private function getService(string $source): EmailLogsAppApiService|EmailLogsClientApiService
    {
        return match (strtolower($source)) {
            'app' => $this->appService,
            'client' => $this->clientService,
            default => throw new \InvalidArgumentException("Invalid source: {$source}"),
        };
    }
}
