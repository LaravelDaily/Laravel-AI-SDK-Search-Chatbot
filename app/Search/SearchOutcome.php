<?php

namespace App\Search;

enum SearchOutcome: string
{
    case Ok = 'ok';
    case Clarification = 'clarification';
    case Failed = 'failed';
    case Blocked = 'blocked';
}
