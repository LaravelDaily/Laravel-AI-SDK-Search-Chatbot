<x-layouts::app :title="__('AI search logs')">
    <div class="flex w-full flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('AI search logs') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Recent natural-language searches for your company, including generated SQL.') }}</flux:text>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('When') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('User') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Question') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Outcome') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Rows') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Tokens') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Cost') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Generated SQL') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b border-zinc-100 align-top dark:border-zinc-800">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at?->toDayDateTimeString() }}</td>
                            <td class="px-4 py-3">{{ $log->user->name }}</td>
                            <td class="px-4 py-3">{{ $log->question }}</td>
                            <td class="px-4 py-3">{{ $log->outcome->value }}</td>
                            <td class="px-4 py-3">{{ $log->row_count ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->input_tokens }} / {{ $log->output_tokens }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->estimated_cost_cents }}¢</td>
                            <td class="px-4 py-3">
                                @if (filled($log->compiled_sql))
                                    <pre class="max-w-xl overflow-x-auto text-xs">{{ $log->compiled_sql }}</pre>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-zinc-500">{{ __('No searches have been logged yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
