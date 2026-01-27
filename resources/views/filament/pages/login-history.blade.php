<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Statistics Cards -->
        @if(!empty($meta['total']))
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-filament::section>
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-blue-400">Total Records</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($meta['total']) }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Showing</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($meta['from'] ?? 0) }} - {{ number_format($meta['to'] ?? 0) }}</p>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Per Page</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $perPage }}</p>
                    </div>
                </x-filament::section>
            </div>
        @endif

        <!-- Filters Section -->
        <x-filament::section>
            <x-slot name="heading">
                Filters
            </x-slot>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <!-- Search Input -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Search
                    </label>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by email or IP..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Logged In From
                    </label>
                    <input
                        type="date"
                        wire:model.live="loggedInFrom"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Logged In To
                    </label>
                    <input
                        type="date"
                        wire:model.live="loggedInTo"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4 mt-4">
                <!-- Action Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Action
                    </label>
                    <select
                        wire:model.live="actionFilter"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="">All Actions</option>
                        @foreach($this->getActionOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Status
                    </label>
                    <select
                        wire:model.live="statusFilter"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="">All Statuses</option>
                        @foreach($this->getStatusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Per Page & Reset Filters -->
            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Items per page:
                    </label>
                    <select
                        wire:model.live="perPage"
                        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                @if($search || $loggedInFrom || $loggedInTo || $actionFilter || $statusFilter)
                    <x-filament::button
                        wire:click="resetFilters"
                        color="gray"
                        size="sm"
                    >
                        Reset Filters
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>

        <!-- Login History Table Section -->
        <x-filament::section>
            <x-slot name="heading">
                Login History
                @if(!empty($meta['total']))
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        ({{ number_format($meta['total']) }} total)
                    </span>
                @endif
            </x-slot>

            @if($isLoading)
                <div class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                    <span class="ml-3 text-gray-600 dark:text-gray-400">Loading login history...</span>
                </div>
            @elseif($errorMessage)
                <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 p-4">
                    <p class="text-danger-800 dark:text-danger-200 mb-4">{{ $errorMessage }}</p>
                    <x-filament::button
                        wire:click="loadLoginHistory"
                        color="danger"
                        size="sm"
                    >
                        Retry
                    </x-filament::button>
                </div>
            @elseif(empty($records))
                <div class="text-center py-12">
                    <p class="text-gray-600 dark:text-gray-400">No login history found.</p>
                    @if($search || $loggedInFrom || $loggedInTo || $actionFilter || $statusFilter)
                        <div class="mt-4">
                            <x-filament::button
                                wire:click="resetFilters"
                                color="gray"
                                size="sm"
                            >
                                Clear Filters
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @else
                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    User
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Action
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    IP Address
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Device
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Logged In
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            @foreach($records as $record)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                                    <span class="text-primary-600 dark:text-primary-400 font-medium text-sm">
                                                        {{ strtoupper(substr($record['user_email'] ?? '?', 0, 1)) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $record['user_email'] ?? 'N/A' }}
                                                </div>
                                                @if(!empty($record['user_id']))
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        ID: {{ $record['user_id'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @php
                                            $actionColor = match($record['action'] ?? '') {
                                                'login' => 'success',
                                                'logout' => 'warning',
                                                'register' => 'info',
                                                default => 'gray',
                                            };
                                        @endphp
                                        <x-filament::badge :color="$actionColor">
                                            {{ ucfirst($record['action'] ?? 'N/A') }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $record['ip_address'] ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $record['device_name'] ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if(!empty($record['logged_in_at']))
                                            {{ \Carbon\Carbon::parse($record['logged_in_at'])->format('Y-m-d H:i') }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if($record['successful'] ?? false)
                                            <x-filament::badge color="success">
                                                Success
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="danger">
                                                Failed
                                            </x-filament::badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if(!empty($meta['last_page']) && $meta['last_page'] > 1)
                    <div class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Showing {{ $meta['from'] ?? 0 }} to {{ $meta['to'] ?? 0 }} of {{ number_format($meta['total'] ?? 0) }} results
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Previous Button -->
                            @if($meta['current_page'] > 1)
                                <x-filament::button
                                    wire:click="goToPage({{ $meta['current_page'] - 1 }})"
                                    color="gray"
                                    size="sm"
                                >
                                    Previous
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    color="gray"
                                    size="sm"
                                    disabled
                                >
                                    Previous
                                </x-filament::button>
                            @endif

                            <!-- Page Numbers -->
                            <div class="flex items-center gap-1">
                                @php
                                    $startPage = max(1, $meta['current_page'] - 2);
                                    $endPage = min($meta['last_page'], $meta['current_page'] + 2);
                                @endphp

                                @if($startPage > 1)
                                    <x-filament::button
                                        wire:click="goToPage(1)"
                                        color="gray"
                                        size="sm"
                                    >
                                        1
                                    </x-filament::button>
                                    @if($startPage > 2)
                                        <span class="px-2 text-gray-500">...</span>
                                    @endif
                                @endif

                                @for($page = $startPage; $page <= $endPage; $page++)
                                    @if($page == $meta['current_page'])
                                        <x-filament::button
                                            color="primary"
                                            size="sm"
                                            disabled
                                        >
                                            {{ $page }}
                                        </x-filament::button>
                                    @else
                                        <x-filament::button
                                            wire:click="goToPage({{ $page }})"
                                            color="gray"
                                            size="sm"
                                        >
                                            {{ $page }}
                                        </x-filament::button>
                                    @endif
                                @endfor

                                @if($endPage < $meta['last_page'])
                                    @if($endPage < $meta['last_page'] - 1)
                                        <span class="px-2 text-gray-500">...</span>
                                    @endif
                                    <x-filament::button
                                        wire:click="goToPage({{ $meta['last_page'] }})"
                                        color="gray"
                                        size="sm"
                                    >
                                        {{ $meta['last_page'] }}
                                    </x-filament::button>
                                @endif
                            </div>

                            <!-- Next Button -->
                            @if($meta['current_page'] < $meta['last_page'])
                                <x-filament::button
                                    wire:click="goToPage({{ $meta['current_page'] + 1 }})"
                                    color="gray"
                                    size="sm"
                                >
                                    Next
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    color="gray"
                                    size="sm"
                                    disabled
                                >
                                    Next
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
