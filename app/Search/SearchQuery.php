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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $filters = [];

        foreach ($data['filters'] ?? [] as $filter) {
            if (! is_array($filter) || ! isset($filter['field'], $filter['operator'])) {
                continue;
            }

            $filters[] = new Filter(
                field: (string) $filter['field'],
                operator: (string) $filter['operator'],
                value: $filter['value'] ?? null,
            );
        }

        $aggregate = null;

        if (is_array($data['aggregate'] ?? null) && isset($data['aggregate']['function'], $data['aggregate']['field'])) {
            $aggregate = new Aggregate(
                function: (string) $data['aggregate']['function'],
                field: (string) $data['aggregate']['field'],
                groupBy: isset($data['aggregate']['groupBy']) ? (string) $data['aggregate']['groupBy'] : null,
            );
        }

        return new self(
            entity: (string) ($data['entity'] ?? ''),
            filters: $filters,
            sort: isset($data['sort']) ? (string) $data['sort'] : null,
            direction: (string) ($data['direction'] ?? 'desc'),
            limit: (int) ($data['limit'] ?? 50),
            aggregate: $aggregate,
        );
    }

    /**
     * @param  list<Filter>  $filters
     */
    public function withFilters(array $filters): self
    {
        return new self(
            entity: $this->entity,
            filters: $filters,
            sort: $this->sort,
            direction: $this->direction,
            limit: $this->limit,
            aggregate: $this->aggregate,
        );
    }

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
