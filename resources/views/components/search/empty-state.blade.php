@props(['message' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center dark:border-zinc-700']) }}>
    <flux:text>{{ $message ?? __('No matching rows.') }}</flux:text>
</div>
