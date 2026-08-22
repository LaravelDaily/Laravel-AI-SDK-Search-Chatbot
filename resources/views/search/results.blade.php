<x-layouts::app :title="__('Search results')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Search results') }}</flux:heading>
            <flux:text class="mt-2">{{ $question }}</flux:text>
        </div>

        <form method="GET" action="{{ route('search.results') }}" class="flex flex-col gap-4">
            <flux:field>
                <flux:label>{{ __('Question') }}</flux:label>
                <flux:input name="q" type="text" :value="$question" required />
            </flux:field>
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
                <flux:button :href="route('search.index')" variant="ghost">{{ __('Open interactive search') }}</flux:button>
            </div>
        </form>

        <x-search.interpretation :interpretation="$attempt->interpretation" />

        @if ($attempt->showsDisambiguation())
            <x-search.disambiguation :candidates="$attempt->candidates" :message="$attempt->message" />
        @elseif ($attempt->showsResults() && $attempt->result->rows->isEmpty())
            <x-search.empty-state />
        @elseif ($attempt->showsResults())
            <x-search.result-table :result="$attempt->result" :actor="auth()->user()" />
        @elseif (filled($attempt->message))
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm font-medium">{{ str($attempt->outcome->value)->headline() }}</p>
                <p class="mt-1 text-sm">{{ $attempt->message }}</p>
            </div>
        @endif

        @if (auth()->user()?->isAdmin() && ($attempt->query !== null || filled($attempt->result?->sql)))
            <details class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <summary class="cursor-pointer text-sm font-medium">{{ __('Show generated query') }}</summary>
                @if ($attempt->query !== null)
                    <pre class="mt-3 overflow-x-auto text-xs">{{ json_encode($attempt->query->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @endif
                @if (filled($attempt->result?->sql))
                    <pre class="mt-3 overflow-x-auto text-xs">{{ $attempt->result->sql }}</pre>
                @endif
            </details>
        @endif
    </div>
</x-layouts::app>
