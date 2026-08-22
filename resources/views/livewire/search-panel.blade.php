<div class="flex flex-col gap-6">
    <form method="GET" action="{{ route('search.results') }}" wire:submit.prevent="search" class="flex flex-col gap-4">
        <flux:field>
            <flux:label>{{ __('Question') }}</flux:label>
            <flux:input
                name="q"
                type="text"
                wire:model="question"
                placeholder="Show me all of customer John Smith's requests in the last year"
                required
            />
            <flux:error name="question" />
        </flux:field>

        <div>
            <flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
        </div>
    </form>

    @if (filled($interpretation))
        <x-search.interpretation :interpretation="$interpretation" />
    @endif

    @if ($this->showingResults() && $this->filters() !== [])
        <div class="flex flex-col gap-3">
            <flux:text class="text-sm font-medium">{{ __('Applied filters') }}</flux:text>

            <div class="flex flex-wrap gap-2">
                @foreach ($this->filters() as $index => $filter)
                    <div
                        wire:key="filter-{{ $index }}-{{ $filter['field'] }}"
                        class="inline-flex items-center gap-1 rounded-full border border-zinc-200 bg-zinc-50 px-2 py-1 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <button
                            type="button"
                            wire:click="editFilter({{ $index }})"
                            class="px-1"
                        >
                            {{ $filter['field'] }}
                            {{ $filter['operator'] }}
                            {{ \App\Search\SearchResultPresenter::formatFilterValue($filter['value']) }}
                        </button>
                        <flux:button
                            type="button"
                            size="sm"
                            variant="ghost"
                            icon="x-mark"
                            wire:click="removeFilter({{ $index }})"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($editingIndex !== null)
        <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 md:grid-cols-3 dark:border-zinc-700">
            <flux:field>
                <flux:label>{{ __('Field') }}</flux:label>
                <select
                    wire:model.live="draftFilter.field"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                >
                    @foreach ($this->editableFields() as $field)
                        <option value="{{ $field }}">{{ $field }}</option>
                    @endforeach
                </select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Operator') }}</flux:label>
                <select
                    wire:model="draftFilter.operator"
                    class="w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-800"
                >
                    @foreach ($this->editableOperators() as $operator)
                        <option value="{{ $operator }}">{{ $operator }}</option>
                    @endforeach
                </select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Value') }}</flux:label>
                <flux:input type="text" wire:model="draftFilter.value" />
            </flux:field>

            <div class="flex gap-2 md:col-span-3">
                <flux:button type="button" variant="primary" wire:click="applyFilterEdit">{{ __('Apply filter') }}</flux:button>
                <flux:button type="button" variant="ghost" wire:click="cancelFilterEdit">{{ __('Cancel') }}</flux:button>
            </div>
        </div>
    @endif

    @if ($this->showingClarification())
        <x-search.disambiguation :candidates="$candidates" :message="$message" />
    @elseif ($this->showingResults() && $tableRows === [])
        <x-search.empty-state />
    @elseif ($this->showingResults())
        <x-search.result-table
            :headings="$tableHeadings"
            :rows="$tableRows"
            :total="$tableTotal"
            :truncated="$tableTruncated"
        />
    @elseif (filled($message))
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <p class="text-sm font-medium">{{ str($outcome)->headline() }}</p>
            <p class="mt-1 text-sm">{{ $message }}</p>
        </div>
    @endif

    @if (auth()->user()?->isAdmin() && ($appliedQuery !== null || filled($sql)))
        <details class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <summary class="cursor-pointer text-sm font-medium">{{ __('Show generated query') }}</summary>
            @if ($appliedQuery !== null)
                <pre class="mt-3 overflow-x-auto text-xs">{{ json_encode($appliedQuery, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
            @if (filled($sql))
                <pre class="mt-3 overflow-x-auto text-xs">{{ $sql }}</pre>
            @endif
        </details>
    @endif
</div>
