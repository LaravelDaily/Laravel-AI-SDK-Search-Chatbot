<?php

namespace App\Search;

enum EntityResolutionOutcome: string
{
    case Resolved = 'resolved';
    case Ambiguous = 'ambiguous';
    case NotFound = 'not_found';
    case Unchanged = 'unchanged';
}
