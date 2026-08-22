@props(['interpretation'])

@if (filled($interpretation))
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 p-4 dark:border-zinc-700']) }}>
        <p class="text-sm font-medium">{{ __('Interpretation') }}</p>
        <p class="mt-1 text-sm">{{ $interpretation }}</p>
    </div>
@endif
