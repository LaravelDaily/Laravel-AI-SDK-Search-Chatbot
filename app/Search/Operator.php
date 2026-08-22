<?php

namespace App\Search;

enum Operator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case LessThan = '<';
    case GreaterThanOrEqual = '>=';
    case LessThanOrEqual = '<=';
    case In = 'in';
    case Contains = 'contains';
    case Between = 'between';

    public function requiresList(): bool
    {
        return $this === self::In;
    }

    public function requiresPair(): bool
    {
        return $this === self::Between;
    }
}
