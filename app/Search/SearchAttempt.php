<?php

namespace App\Search;

use App\Models\AiSearchLog;

final readonly class SearchAttempt
{
    /**
     * @param  list<array{id: int, name: string, email: string|null}>  $candidates
     */
    public function __construct(
        public SearchOutcome $outcome,
        public string $question,
        public ?string $interpretation,
        public ?string $message,
        public ?SearchQuery $query,
        public ?SearchResult $result,
        public array $candidates,
        public AiSearchLog $log,
        public int $attempts,
        public ?string $confidence = null,
    ) {}

    public function showsResults(): bool
    {
        return $this->outcome === SearchOutcome::Ok && $this->result instanceof SearchResult;
    }

    public function showsDisambiguation(): bool
    {
        return $this->outcome === SearchOutcome::Clarification;
    }
}
