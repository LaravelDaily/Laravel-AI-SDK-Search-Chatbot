@props([
    'candidates' => [],
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700']) }}>
    @if (filled($message))
        <flux:text>{{ $message }}</flux:text>
    @endif

    @if ($candidates !== [])
        <div class="flex flex-col gap-2">
            <flux:heading size="lg">{{ __('Which customer did you mean?') }}</flux:heading>
            <ul class="list-disc ps-6 text-sm">
                @foreach ($candidates as $candidate)
                    <li>{{ $candidate['name'] }}{{ filled($candidate['email']) ? ' ('.$candidate['email'].')' : '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
