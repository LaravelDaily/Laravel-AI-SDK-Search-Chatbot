<?php

namespace App\Search;

use Illuminate\Database\Eloquent\Model;

final readonly class EntitySchema
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, FieldSchema>  $fields
     * @param  list<string>  $sortKeys
     * @param  list<string>  $eagerLoad
     * @param  list<string>  $select
     */
    public function __construct(
        public string $name,
        public string $model,
        public array $fields,
        public array $sortKeys,
        public string $defaultSort,
        public array $eagerLoad = [],
        public array $select = [],
        public string $tenantColumn = 'company_id',
    ) {}

    public function field(string $name): ?FieldSchema
    {
        return $this->fields[$name] ?? null;
    }

    public function allowsSort(string $key): bool
    {
        return in_array($key, $this->sortKeys, true);
    }
}
