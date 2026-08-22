<?php

namespace App\Search;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

final class InvalidSearchQuery extends Exception implements ShouldntReport
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
