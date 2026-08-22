<?php

namespace App\Search;

use Laravel\Ai\Responses\Data\Usage;

final class TokenCostEstimator
{
    public function cents(Usage $usage): int
    {
        $inputRate = (int) config('search.cost.input_cents_per_million_tokens');
        $outputRate = (int) config('search.cost.output_cents_per_million_tokens');

        $micros = ($usage->promptTokens * $inputRate) + ($usage->completionTokens * $outputRate);

        return (int) round($micros / 1_000_000);
    }
}
