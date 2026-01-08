<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->systemInfo)
            <x-filament::section>
                <x-slot name="heading">
                    System Information
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <strong>Laravel Version:</strong> {{ $this->systemInfo['laravel_version'] ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>PHP Version:</strong> {{ $this->systemInfo['php_version'] ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>Environment:</strong> 
                        <span class="badge badge-{{ $this->systemInfo['environment'] === 'production' ? 'success' : 'warning' }}">
                            {{ $this->systemInfo['environment'] ?? 'N/A' }}
                        </span>
                    </div>
                    <div>
                        <strong>Debug Mode:</strong> 
                        <span class="badge badge-{{ $this->systemInfo['debug_mode'] ? 'danger' : 'success' }}">
                            {{ $this->systemInfo['debug_mode'] ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div>
                        <strong>Timezone:</strong> {{ $this->systemInfo['timezone'] ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>Database:</strong> {{ $this->systemInfo['database_connection'] ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>Cache Driver:</strong> {{ $this->systemInfo['cache_driver'] ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>Queue Connection:</strong> {{ $this->systemInfo['queue_connection'] ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>Mail Driver:</strong> {{ $this->systemInfo['mail_driver'] ?? 'N/A' }}
                    </div>
                    <div>
                        <strong>Storage Disk:</strong> {{ $this->systemInfo['storage_disk'] ?? 'N/A' }}
                    </div>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p>Unable to load system information. Please try again.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
