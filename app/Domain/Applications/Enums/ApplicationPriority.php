<?php

namespace App\Domain\Applications\Enums;

enum ApplicationPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Normal => 'warning',
            self::High => 'danger',
        };
    }
}
