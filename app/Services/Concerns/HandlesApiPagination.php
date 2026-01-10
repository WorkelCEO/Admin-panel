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
 *         "current_page": 1,
 *         "data": [...],
 *         "first_page_url": "...",
 *         "from": 1,
 *         "last_page": 5,
 *         "last_page_url": "...",
 *         "next_page_url": "...",
 *         "path": "...",
 *         "per_page": 20,
 *         "prev_page_url": null,
 *         "to": 5,
 *         "total": 100
 *     }
 * }
 * 
 * BaseApiService extracts response.data into ApiResponse->data, so $response->data
 * contains the pagination object with items at 'data' key and metadata at root level.
 */
trait HandlesApiPagination
{
    /**
     * Extract paginated data from API response
     * 
     * @param ApiResponse $response The API response from BaseApiService
     * @return array{data: array, meta: array{current_page: int, per_page: int, total: int, last_page: int, from: int|null, to: int|null}}
     */
    protected function extractPaginatedData(ApiResponse $response): array
    {
        $responseData = $response->data ?? [];
        
        // Check if this is a paginated response (has 'data' key with array and pagination metadata)
        if (isset($responseData['data']) && is_array($responseData['data']) && isset($responseData['current_page'])) {
            // Extract items from data.data
            $items = $responseData['data'];
            
            // Extract pagination metadata from data level
            // According to API docs: current_page, per_page, total, last_page, from, to are all at data level
            $meta = [
                'current_page' => (int) ($responseData['current_page'] ?? 1),
                'per_page' => (int) ($responseData['per_page'] ?? 15),
                'total' => (int) ($responseData['total'] ?? 0),
                'last_page' => (int) ($responseData['last_page'] ?? 1),
                'from' => isset($responseData['from']) ? (int) $responseData['from'] : null,
                'to' => isset($responseData['to']) ? (int) $responseData['to'] : null,
            ];
            
            return [
                'data' => is_array($items) ? $items : [],
                'meta' => $meta,
            ];
        }
        
        // Fallback: if data is directly an array (non-paginated response)
        // This handles endpoints that return arrays without pagination
        if (is_array($responseData) && !isset($responseData['data']) && !isset($responseData['current_page'])) {
            $itemCount = count($responseData);
            return [
                'data' => $responseData,
                'meta' => [
                    'current_page' => 1,
                    'per_page' => $itemCount > 0 ? $itemCount : 15,
                    'total' => $itemCount,
                    'last_page' => 1,
                    'from' => $itemCount > 0 ? 1 : null,
                    'to' => $itemCount > 0 ? $itemCount : null,
                ],
            ];
        }
        
        // Fallback: if data is a single object (non-paginated single item response)
        if (!is_array($responseData) || (is_array($responseData) && !isset($responseData['data']) && !isset($responseData['current_page']))) {
            // Try to determine if it's a single item or empty
            if (empty($responseData)) {
                return [
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                        'last_page' => 1,
                        'from' => null,
                        'to' => null,
                    ],
                ];
            }
            
            // Single item - wrap in array for consistency
            return [
                'data' => [$responseData],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 1,
                    'last_page' => 1,
                    'from' => 1,
                    'to' => 1,
                ],
            ];
        }
        
        // Empty or malformed response
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
            ],
        ];
    }
}