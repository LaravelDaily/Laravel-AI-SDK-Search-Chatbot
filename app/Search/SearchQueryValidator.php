<?php

namespace App\Search;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class SearchQueryValidator
{
    /**
     * @return list<string>
     */
    private const array AGGREGATE_FUNCTIONS = ['count', 'sum', 'avg'];

    public function validate(SearchQuery $query): SearchQuery
    {
        $entity = SearchSchema::entity($query->entity);

        if ($entity === null) {
            $allowed = implode(', ', SearchSchema::entityNames());

            throw new InvalidSearchQuery("Unknown entity '{$query->entity}'. Allowed entities: {$allowed}.");
        }

        if ($query->limit < 1) {
            throw new InvalidSearchQuery('Limit must be at least 1.');
        }

        if ($query->limit > SearchSchema::MAX_LIMIT) {
            throw new InvalidSearchQuery('Limit cannot exceed '.SearchSchema::MAX_LIMIT.'.');
        }

        if (! in_array(strtolower($query->direction), ['asc', 'desc'], true)) {
            throw new InvalidSearchQuery("Direction must be 'asc' or 'desc'.");
        }

        if ($query->sort !== null && ! $entity->allowsSort($query->sort)) {
            throw new InvalidSearchQuery("Unknown sort key '{$query->sort}' on entity '{$entity->name}'.");
        }

        foreach ($query->filters as $filter) {
            $this->validateFilter($entity, $filter);
        }

        if ($query->aggregate !== null) {
            $this->validateAggregate($entity, $query->aggregate);
        }

        return $query;
    }

    private function validateFilter(EntitySchema $entity, Filter $filter): void
    {
        $field = $entity->field($filter->field);

        if ($field === null) {
            throw new InvalidSearchQuery("Unknown field '{$filter->field}' on entity '{$entity->name}'.");
        }

        if ($field->denied) {
            throw new InvalidSearchQuery("Field '{$field->name}' is not allowed.");
        }

        $operator = Operator::tryFrom($filter->operator);

        if ($operator === null || ! $field->allows($operator)) {
            throw new InvalidSearchQuery(
                "Operator '{$filter->operator}' is not allowed for {$field->type->value} field '{$field->name}'.",
            );
        }

        if ($operator->requiresList()) {
            $this->validateListValue($field, $filter->value);

            return;
        }

        if ($operator->requiresPair()) {
            $this->validatePairValue($field, $filter->value);

            return;
        }

        $this->validateScalarValue($field, $filter->value);
    }

    private function validateAggregate(EntitySchema $entity, Aggregate $aggregate): void
    {
        $function = strtolower($aggregate->function);

        if (! in_array($function, self::AGGREGATE_FUNCTIONS, true)) {
            throw new InvalidSearchQuery("Unknown aggregate function '{$aggregate->function}'.");
        }

        $field = $entity->field($aggregate->field);

        if ($field === null) {
            throw new InvalidSearchQuery("Unknown field '{$aggregate->field}' on entity '{$entity->name}'.");
        }

        if ($field->denied) {
            throw new InvalidSearchQuery("Field '{$field->name}' is not allowed.");
        }

        if ($function === 'count' && ! $field->aggregatable) {
            throw new InvalidSearchQuery("Field '{$field->name}' cannot be used with count.");
        }

        if (in_array($function, ['sum', 'avg'], true) && ($field->type !== FieldType::Integer || ! $field->aggregatable)) {
            throw new InvalidSearchQuery("Field '{$field->name}' cannot be used with {$function}.");
        }

        if ($aggregate->groupBy === null) {
            return;
        }

        $groupBy = $entity->field($aggregate->groupBy);

        if ($groupBy === null || $groupBy->denied || $groupBy->isRelation()) {
            throw new InvalidSearchQuery("Unknown groupBy field '{$aggregate->groupBy}' on entity '{$entity->name}'.");
        }
    }

    private function validateListValue(FieldSchema $field, mixed $value): void
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidSearchQuery("Operator 'in' requires a non-empty list of values.");
        }

        foreach ($value as $item) {
            $this->validateScalarValue($field, $item);
        }
    }

    private function validatePairValue(FieldSchema $field, mixed $value): void
    {
        if (! is_array($value) || count($value) !== 2) {
            throw new InvalidSearchQuery("Operator 'between' requires exactly two values.");
        }

        foreach (array_values($value) as $item) {
            $this->validateScalarValue($field, $item);
        }
    }

    private function validateScalarValue(FieldSchema $field, mixed $value): void
    {
        match ($field->type) {
            FieldType::Integer => $this->validateInteger($field, $value),
            FieldType::String => $this->validateString($field, $value),
            FieldType::Date, FieldType::DateTime => $this->validateDate($field, $value),
            FieldType::Enum => $this->validateEnum($field, $value),
            FieldType::Boolean => $this->validateBoolean($field, $value),
        };
    }

    private function validateInteger(FieldSchema $field, mixed $value): void
    {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
            return;
        }

        throw new InvalidSearchQuery("Field '{$field->name}' requires an integer value.");
    }

    private function validateString(FieldSchema $field, mixed $value): void
    {
        if (! is_string($value)) {
            throw new InvalidSearchQuery("Field '{$field->name}' requires a string value.");
        }
    }

    private function validateDate(FieldSchema $field, mixed $value): void
    {
        if ($this->parseDate($value) === null) {
            $display = is_scalar($value) ? (string) $value : get_debug_type($value);

            throw new InvalidSearchQuery("Invalid date value '{$display}' for field '{$field->name}'.");
        }
    }

    private function validateEnum(FieldSchema $field, mixed $value): void
    {
        $stringValue = match (true) {
            $value instanceof \BackedEnum => (string) $value->value,
            is_string($value) => $value,
            default => null,
        };

        if ($stringValue === null) {
            throw new InvalidSearchQuery("Field '{$field->name}' requires a string value.");
        }

        if ($field->enumValues !== null && ! in_array($stringValue, $field->enumValues, true)) {
            $allowed = implode(', ', $field->enumValues);

            throw new InvalidSearchQuery(
                "Invalid value '{$stringValue}' for field '{$field->name}'. Allowed values: {$allowed}.",
            );
        }
    }

    private function validateBoolean(FieldSchema $field, mixed $value): void
    {
        if (! is_bool($value)) {
            throw new InvalidSearchQuery("Field '{$field->name}' requires a boolean value.");
        }
    }

    public function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        foreach (['Y-m-d', 'Y-m-d H:i:s'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        return null;
    }
}
