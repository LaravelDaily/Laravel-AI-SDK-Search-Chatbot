<?php

namespace App\Search\Naive;

final readonly class FailureComparison
{
    public function __construct(
        public string $mode,
        public string $naive,
        public string $guarded,
    ) {}
}
