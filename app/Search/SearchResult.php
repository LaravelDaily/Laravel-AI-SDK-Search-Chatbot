<?php

namespace App\Search;

use Illuminate\Support\Collection;

final readonly class SearchResult
{
    /**
     * @param  Collection<int, mixed>  $rows
     */
    public function __construct(
        public Collection $rows,
        public int $total,
        public bool $truncated,
        public SearchQuery $appliedQuery,
        public string $sql,
    ) {}
}
