<x-layouts::app :title="__('Search')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Search') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Ask a question about customers, service requests, or invoices.') }}</flux:text>
        </div>

        <form method="GET" action="{{ route('search.results') }}" class="flex flex-col gap-4">
            <flux:field>
                <flux:label>{{ __('Question') }}</flux:label>
                <flux:input
                    name="q"
                    type="text"
                    :value="old('q')"
                    placeholder="Show me all of customer John Smith's requests in the last year"
                    required
                />
            </flux:field>

            <div>
                <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
