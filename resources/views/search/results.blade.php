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
            <div>
                <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
            </div>
        </form>

        @if ($attempt->interpretation)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm font-medium">{{ __('Interpretation') }}</p>
                <p class="mt-1 text-sm">{{ $attempt->interpretation }}</p>
            </div>
        @endif

        @if (filled($attempt->message))
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <p class="text-sm font-medium">{{ str($attempt->outcome->value)->headline() }}</p>
                <p class="mt-1 text-sm">{{ $attempt->message }}</p>
            </div>
        @endif

        @if ($attempt->candidates !== [])
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">{{ __('Which customer did you mean?') }}</flux:heading>
                <ul class="list-disc ps-6 text-sm">
                    @foreach ($attempt->candidates as $candidate)
                        <li>{{ $candidate['name'] }}{{ filled($candidate['email']) ? ' ('.$candidate['email'].')' : '' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attempt->result)
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('ID') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Summary') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attempt->result->rows as $row)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-4 py-3">{{ data_get($row, 'id', '—') }}</td>
                                <td class="px-4 py-3">{{ data_get($row, 'title') ?? data_get($row, 'name') ?? data_get($row, 'aggregate') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-zinc-500">{{ __('No matching rows.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <flux:text>{{ __('Total: :count', ['count' => $attempt->result->total]) }}</flux:text>
        @endif

        @if (auth()->user()?->isAdmin() && filled($attempt->result?->sql))
            <details class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <summary class="cursor-pointer text-sm font-medium">{{ __('Show generated query') }}</summary>
                <pre class="mt-3 overflow-x-auto text-xs">{{ $attempt->result->sql }}</pre>
            </details>
        @endif
    </div>
</x-layouts::app>
