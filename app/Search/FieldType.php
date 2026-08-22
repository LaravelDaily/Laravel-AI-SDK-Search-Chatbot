<?php

namespace App\Search;

enum FieldType: string
{
    case Integer = 'integer';
    case String = 'string';
    case Date = 'date';
    case DateTime = 'datetime';
    case Enum = 'enum';
    case Boolean = 'boolean';

    /**
     * @return list<Operator>
     */
    public function operators(): array
    {
        return match ($this) {
            self::String => [
                Operator::Equals,
                Operator::NotEquals,
                Operator::Contains,
                Operator::In,
            ],
            self::Integer => [
                Operator::Equals,
                Operator::NotEquals,
                Operator::GreaterThan,
                Operator::LessThan,
                Operator::GreaterThanOrEqual,
                Operator::LessThanOrEqual,
                Operator::In,
                Operator::Between,
            ],
            self::Date, self::DateTime => [
                Operator::Equals,
                Operator::NotEquals,
                Operator::GreaterThan,
                Operator::LessThan,
                Operator::GreaterThanOrEqual,
                Operator::LessThanOrEqual,
                Operator::Between,
            ],
            self::Enum => [
                Operator::Equals,
                Operator::NotEquals,
                Operator::In,
            ],
            self::Boolean => [
                Operator::Equals,
                Operator::NotEquals,
            ],
        };
    }
}
