<?php

namespace App\Enums;

enum AccessStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
