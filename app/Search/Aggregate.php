<?php

namespace App\Search;

final readonly class Aggregate
{
    public function __construct(
        public string $function,
        public string $field,
        public ?string $groupBy = null,
    ) {}
}
