@props([
    'result' => null,
    'actor' => null,
    'headings' => null,
    'rows' => null,
    'total' => null,
    'truncated' => false,
])

@php
    if ($result instanceof \App\Search\SearchResult && $actor instanceof \App\Models\User) {
        $table = \App\Search\SearchResultPresenter::present($result, $actor);
        $headings = $table['headings'];
        $rows = $table['rows'];
        $total = $table['total'];
        $truncated = $table['truncated'];
    }

    $headings = $headings ?? [];
    $rows = $rows ?? [];
    $total = $total ?? 0;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
                <tr>
                    @foreach ($headings as $heading)
                        <th class="px-4 py-3 font-medium">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-zinc-100 dark:border-zinc-800">
                        @foreach ($row as $cell)
                            <td class="px-4 py-3">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <flux:text>{{ __('Total: :count', ['count' => $total]) }}</flux:text>

    @if ($truncated)
        <flux:text>{{ __('Results were truncated to keep the response bounded.') }}</flux:text>
    @endif
</div>
