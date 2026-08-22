<?php

namespace App\Search;

final readonly class Filter
{
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value,
    ) {}
}
