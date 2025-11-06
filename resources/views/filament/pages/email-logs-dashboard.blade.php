<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header Widgets (Statistics) will be rendered here by Filament -->
        
        <!-- Additional Information -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Quick Actions Card -->
            <x-filament::section>
                <x-slot name="heading">
                    Quick Actions
                </x-slot>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium">View All Email Logs</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Browse and filter all email logs</p>
                        </div>
                        <x-filament::button
                            tag="a"
                            href="{{ route('filament.admin.resources.email-logs.index') }}"
                            size="sm"
                        >
                            View Logs
                        </x-filament::button>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium">Failed Emails</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">View emails that failed to send</p>
                        </div>
                        <x-filament::button
                            tag="a"
                            href="{{ route('filament.admin.resources.email-logs.index', ['tableFilters[status][value]' => 'error']) }}"
                            size="sm"
                            color="danger"
                        >
                            View Failed
                        </x-filament::button>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium">Today's Emails</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">View emails sent today</p>
                        </div>
                        <x-filament::button
                            tag="a"
                            href="{{ route('filament.admin.resources.email-logs.index', ['tableFilters[date_range][date_from]' => date('Y-m-d'), 'tableFilters[date_range][date_to]' => date('Y-m-d')]) }}"
                            size="sm"
                            color="primary"
                        >
                            View Today
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Email Types Info Card -->
            <x-filament::section>
                <x-slot name="heading">
                    Email Types
                </x-slot>
                
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-600 dark:text-gray-300">Verification</span>
                        <x-filament::badge color="primary" size="sm">Account Verification</x-filament::badge>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-600 dark:text-gray-300">Project Invitation</span>
                        <x-filament::badge color="success" size="sm">Project Invites</x-filament::badge>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-600 dark:text-gray-300">Workspace Invitation</span>
                        <x-filament::badge color="info" size="sm">Workspace Invites</x-filament::badge>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-600 dark:text-gray-300">Password Reset</span>
                        <x-filament::badge color="warning" size="sm">Password Recovery</x-filament::badge>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-600 dark:text-gray-300">Unread Chat</span>
                        <x-filament::badge color="gray" size="sm">Chat Notifications</x-filament::badge>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-gray-600 dark:text-gray-300">Registration</span>
                        <x-filament::badge color="danger" size="sm">New User Alerts</x-filament::badge>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <!-- System Status Card -->
        <x-filament::section>
            <x-slot name="heading">
                System Information
            </x-slot>
            
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Auto Refresh</p>
                            <p class="text-sm font-semibold">Every 60s</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">API Status</p>
                            <p class="text-sm font-semibold">Connected</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex items-center space-x-3">
                        <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                            <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Data Range</p>
                            <p class="text-sm font-semibold">Last 30 Days</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>

