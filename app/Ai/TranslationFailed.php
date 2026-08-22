<?php

namespace App\Ai;

use Laravel\Ai\Responses\Data\Usage;

final readonly class TranslationFailed
{
    public function __construct(
        public string $reason,
        public int $attempts,
        public Usage $tokenUsage,
        public ?string $interpretation = null,
    ) {}
}
