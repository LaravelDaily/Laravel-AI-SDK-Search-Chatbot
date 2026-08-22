<?php

namespace App\Search;

final readonly class EntityResolution
{
    /**
     * @param  list<array{id: int, name: string, email: string|null}>  $candidates
     */
    public function __construct(
        public EntityResolutionOutcome $outcome,
        public SearchQuery $query,
        public ?string $message = null,
        public array $candidates = [],
    ) {}
}
