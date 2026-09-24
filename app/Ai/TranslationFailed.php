<?php

namespace App\Ai;

use Laravel\Ai\Responses\Data\TextUsage;

final readonly class TranslationFailed
{
    public function __construct(
        public string $reason,
        public int $attempts,
        public TextUsage $tokenUsage,
        public ?string $interpretation = null,
    ) {}
}
