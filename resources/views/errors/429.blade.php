<x-layouts::app :title="__('Too many searches')">
    <div class="flex w-full flex-1 flex-col gap-4">
        <flux:heading size="xl">{{ __('Too many searches') }}</flux:heading>
        <flux:text>
            {{ $exception->getMessage() ?: __('You have reached the search limit of 20 questions per minute. Please wait a moment and try again.') }}
        </flux:text>
        <div>
            <flux:button :href="route('search.index')" variant="primary">{{ __('Back to search') }}</flux:button>
        </div>
    </div>
</x-layouts::app>
