<?php

namespace App\Search;

use Laravel\Ai\Responses\Data\TextUsage;

final class TokenCostEstimator
{
    public function cents(TextUsage $usage): int
    {
        $inputRate = (int) config('search.cost.input_cents_per_million_tokens');
        $outputRate = (int) config('search.cost.output_cents_per_million_tokens');

        $micros = ($usage->inputTokens * $inputRate) + ($usage->outputTokens * $outputRate);

        return (int) round($micros / 1_000_000);
    }
}
