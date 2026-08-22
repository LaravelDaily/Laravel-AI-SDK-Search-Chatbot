<?php

namespace App\Search;

use Illuminate\Database\Eloquent\Model;

final readonly class FieldSchema
{
    /**
     * @param  list<Operator>  $operators
     * @param  class-string<Model>|null  $relationModel
     * @param  list<string>|null  $enumValues
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public array $operators,
        public bool $sortable = false,
        public bool $denied = false,
        public bool $aggregatable = false,
        public ?string $column = null,
        public ?string $relationModel = null,
        public ?string $relationColumn = null,
        public ?string $relationForeignKey = null,
        public ?array $enumValues = null,
    ) {}

    /**
     * @param  class-string<Model>|null  $relationModel
     * @param  list<string>|null  $enumValues
     */
    public static function make(
        string $name,
        FieldType $type,
        bool $sortable = false,
        bool $denied = false,
        bool $aggregatable = false,
        ?string $column = null,
        ?string $relationModel = null,
        ?string $relationColumn = null,
        ?string $relationForeignKey = null,
        ?array $enumValues = null,
    ): self {
        return new self(
            name: $name,
            type: $type,
            operators: $type->operators(),
            sortable: $sortable,
            denied: $denied,
            aggregatable: $aggregatable,
            column: $column,
            relationModel: $relationModel,
            relationColumn: $relationColumn,
            relationForeignKey: $relationForeignKey,
            enumValues: $enumValues,
        );
    }

    public function column(): string
    {
        return $this->column ?? $this->name;
    }

    public function isRelation(): bool
    {
        return $this->relationModel !== null;
    }

    public function allows(Operator $operator): bool
    {
        return in_array($operator, $this->operators, true);
    }
}
