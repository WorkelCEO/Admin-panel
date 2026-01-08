<?php

namespace App\Filament\Concerns;

/**
 * Trait for extracting table filters in a type-safe way
 */
trait ExtractsTableFilters
{
    /**
     * Extract filter data from Filament table filters
     *
     * @param array|null $filters Table filters array
     * @return array Filter data array
     */
    protected function extractTableFilters(?array $filters = null): array
    {
        $filters = $filters ?? $this->tableFilters ?? [];
        $filterData = [];

        // Extract status filter
        if (isset($filters['status']['value'])) {
            $filterData['status'] = $filters['status']['value'];
        }

        // Extract email_type filter
        if (isset($filters['email_type']['value'])) {
            $filterData['email_type'] = $filters['email_type']['value'];
        }

        // Extract date_range filter
        if (isset($filters['date_range'])) {
            if (!empty($filters['date_range']['date_from'])) {
                $filterData['date_from'] = is_string($filters['date_range']['date_from'])
                    ? $filters['date_range']['date_from']
                    : $filters['date_range']['date_from']->format('Y-m-d');
            }
            if (!empty($filters['date_range']['date_to'])) {
                $filterData['date_to'] = is_string($filters['date_range']['date_to'])
                    ? $filters['date_range']['date_to']
                    : $filters['date_range']['date_to']->format('Y-m-d');
            }
        }

        // Extract recipient_email filter
        if (isset($filters['recipient_email']['recipient_email'])) {
            $filterData['recipient_email'] = $filters['recipient_email']['recipient_email'];
        }

        // Extract sender_email filter
        if (isset($filters['sender_email']['sender_email'])) {
            $filterData['sender_email'] = $filters['sender_email']['sender_email'];
        }

        // Extract search value
        $search = $this->getTableSearch();
        if ($search) {
            $filterData['search'] = $search;
        }

        return $filterData;
    }

    /**
     * Apply tab filters to filter data
     *
     * @param array $filterData Existing filter data
     * @param string|null $activeTab Active tab name
     * @return array Updated filter data
     */
    protected function applyTabFilters(array $filterData, ?string $activeTab = null): array
    {
        $activeTab = $activeTab ?? $this->activeTab ?? 'all';

        if ($activeTab === 'success') {
            $filterData['status'] = 'success';
        } elseif ($activeTab === 'error') {
            $filterData['status'] = 'error';
        } elseif ($activeTab === 'today') {
            $filterData['date_from'] = now()->format('Y-m-d');
            $filterData['date_to'] = now()->format('Y-m-d');
        }

        return $filterData;
    }
}
