<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Statistics Cards -->
        @if(!empty($meta['total']))
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-filament::section>
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-blue-400">Total Users</p>
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
                        placeholder="Search by name or email..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>

                <!-- Role Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Role
                    </label>
                    <select
                        wire:model.live="role"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="">All Roles</option>
                        @foreach($this->getRoleOptions() as $value => $label)
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
                        wire:model.live="status"
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
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                @if($search || $role || $status)
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

        <!-- Users Table Section -->
        <x-filament::section>
            <x-slot name="heading">
                Users
                @if(!empty($meta['total']))
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        ({{ number_format($meta['total']) }} total)
                    </span>
                @endif
            </x-slot>

            @if($isLoading)
                <div class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                    <span class="ml-3 text-gray-600 dark:text-gray-400">Loading users...</span>
                </div>
            @elseif($errorMessage)
                <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 p-4">
                    <p class="text-danger-800 dark:text-danger-200 mb-4">{{ $errorMessage }}</p>
                    <x-filament::button
                        wire:click="loadUsers"
                        color="danger"
                        size="sm"
                    >
                        Retry
                    </x-filament::button>
                </div>
            @elseif(empty($users))
                <div class="text-center py-12">
                    <p class="text-gray-600 dark:text-gray-400">No users found.</p>
                    @if($search || $role || $status)
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
                                    Role
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Email Verified
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Last Seen
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Created At
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                            @foreach($users as $user)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                                    <span class="text-primary-600 dark:text-primary-400 font-medium text-sm">
                                                        {{ strtoupper(substr($user['name'] ?? 'U', 0, 1)) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $user['name'] ?? 'N/A' }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $user['email'] ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @php
                                            $roleColor = match($user['system_role'] ?? 'user') {
                                                'admin' => 'danger',
                                                'pm' => 'warning',
                                                default => 'info',
                                            };
                                        @endphp
                                        <x-filament::badge :color="$roleColor">
                                            {{ ucfirst($user['system_role'] ?? 'user') }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @php
                                            $statusColor = match($user['status'] ?? 'offline') {
                                                'online' => 'success',
                                                'active' => 'success',
                                                default => 'gray',
                                            };
                                        @endphp
                                        <x-filament::badge :color="$statusColor">
                                            {{ ucfirst($user['status'] ?? 'offline') }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if(!empty($user['email_verified_at']))
                                            <x-filament::badge color="success">
                                                Verified
                                            </x-filament::badge>
                                        @else
                                            <x-filament::badge color="warning">
                                                Unverified
                                            </x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if(!empty($user['last_seen']))
                                            {{ \Carbon\Carbon::parse($user['last_seen'])->diffForHumans() }}
                                        @else
                                            Never
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @if(!empty($user['created_at']))
                                            {{ \Carbon\Carbon::parse($user['created_at'])->format('Y-m-d H:i') }}
                                        @else
                                            N/A
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