<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Active = 'active';
    case Churned = 'churned';
    case Prospect = 'prospect';
}
