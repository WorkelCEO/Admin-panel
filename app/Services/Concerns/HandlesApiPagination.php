<?php

namespace App\Services\Concerns;

use App\DTOs\ApiResponse;

/**
 * Trait for handling standardized API pagination responses
 * 
 * According to Admin API documentation, paginated responses follow this format:
 * {
 *     "success": true,
 *     "data": {
 *         "data": [...],
 *         "current_page": 1,
 *         "per_page": 15,
 *         "total": 100,
 *         "last_page": 7
 *     }
 * }
 */
trait HandlesApiPagination
{
    /**
     * Extract paginated data from API response
     * 
     * @param ApiResponse $response The API response
     * @return array{data: array, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    protected function extractPaginatedData(ApiResponse $response): array
    {
        $responseData = $response->data ?? [];
        
        // Standard format: { data: { data: [...], current_page, per_page, total, last_page } }
        if (isset($responseData['data']) && is_array($responseData['data'])) {
            // Extract items from data.data
            $items = $responseData['data'];
            
            // Extract meta from data (current_page, per_page, total, last_page are at the data level)
            $meta = [
                'current_page' => (int) ($responseData['current_page'] ?? 1),
                'per_page' => (int) ($responseData['per_page'] ?? 15),
                'total' => (int) ($responseData['total'] ?? (is_array($items) ? count($items) : 0)),
                'last_page' => (int) ($responseData['last_page'] ?? 1),
            ];
            
            return [
                'data' => is_array($items) ? $items : [],
                'meta' => $meta,
            ];
        }
        
        // Fallback: if data is directly an array (non-paginated response)
        if (is_array($responseData) && !isset($responseData['data'])) {
            return [
                'data' => $responseData,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => count($responseData),
                    'total' => count($responseData),
                    'last_page' => 1,
                ],
            ];
        }
        
        // Empty response
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'last_page' => 1,
            ],
        ];
    }
}