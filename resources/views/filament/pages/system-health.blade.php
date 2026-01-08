<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->systemHealth)
            <x-filament::section>
                <x-slot name="heading">
                    System Health Status
                </x-slot>
                <x-slot name="description">
                    Last checked: {{ now()->format('Y-m-d H:i:s') }}
                </x-slot>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full {{ $this->systemHealth['database'] === 'connected' ? 'bg-success-500' : 'bg-danger-500' }}"></div>
                            <div>
                                <div class="font-semibold">Database</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->systemHealth['database'] ?? 'Unknown' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full {{ $this->systemHealth['cache'] === 'connected' ? 'bg-success-500' : 'bg-danger-500' }}"></div>
                            <div>
                                <div class="font-semibold">Cache</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->systemHealth['cache'] ?? 'Unknown' }}</div>
                            </div>
                        </div>
                    </div>

                    @if($this->systemHealth['redis'])
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full {{ $this->systemHealth['redis'] === 'connected' ? 'bg-success-500' : 'bg-danger-500' }}"></div>
                            <div>
                                <div class="font-semibold">Redis</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->systemHealth['redis'] ?? 'Unknown' }}</div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full {{ $this->systemHealth['storage'] === 'writable' ? 'bg-success-500' : 'bg-danger-500' }}"></div>
                            <div>
                                <div class="font-semibold">Storage</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->systemHealth['storage'] ?? 'Unknown' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full {{ $this->systemHealth['logs'] === 'writable' ? 'bg-success-500' : 'bg-danger-500' }}"></div>
                            <div>
                                <div class="font-semibold">Logs</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $this->systemHealth['logs'] ?? 'Unknown' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <x-filament::button wire:click="refresh">
                        Refresh Status
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p>Unable to load system health. Please try again.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
