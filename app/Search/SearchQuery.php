<?php

namespace App\Search;

final readonly class SearchQuery
{
    /**
     * @param  list<Filter>  $filters
     */
    public function __construct(
        public string $entity,
        public array $filters = [],
        public ?string $sort = null,
        public string $direction = 'desc',
        public int $limit = 50,
        public ?Aggregate $aggregate = null,
    ) {}

    public function withLimit(int $limit): self
    {
        return new self(
            entity: $this->entity,
            filters: $this->filters,
            sort: $this->sort,
            direction: $this->direction,
            limit: $limit,
            aggregate: $this->aggregate,
        );
    }
}
