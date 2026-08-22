<x-layouts::app :title="__('Search')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Search') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Ask a question about customers, service requests, or invoices.') }}</flux:text>
        </div>

        <livewire:search-panel />
    </div>
</x-layouts::app>
