<?php

namespace App\Enums;

enum ServiceRequestPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';
}
