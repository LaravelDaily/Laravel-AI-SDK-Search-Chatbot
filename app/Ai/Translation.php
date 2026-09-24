<?php

namespace App\Ai;

use App\Search\SearchQuery;
use Laravel\Ai\Responses\Data\TextUsage;

final readonly class Translation
{
    public function __construct(
        public ?SearchQuery $query,
        public string $interpretation,
        public string $confidence,
        public ?string $clarification,
        public int $attempts,
        public TextUsage $tokenUsage,
    ) {}

    public function needsClarification(): bool
    {
        return filled($this->clarification) || $this->confidence === 'low';
    }
}
