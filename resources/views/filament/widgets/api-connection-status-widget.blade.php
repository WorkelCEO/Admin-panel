<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            API Connection Status
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach(['app' => 'App API', 'client' => 'Client API'] as $source => $label)
                @php
                    $status = $this->getConnectionStatus($source);
                @endphp
                <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full {{ $status['connected'] ? 'bg-success-500' : 'bg-danger-500' }}"></div>
                        <div>
                            <div class="font-semibold">{{ $label }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $status['message'] }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                Last checked: {{ $status['lastChecked']->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    <x-filament::button
                        size="sm"
                        wire:click="testConnection('{{ $source }}')"
                        wire:loading.attr="disabled"
                    >
                        Test
                    </x-filament::button>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
