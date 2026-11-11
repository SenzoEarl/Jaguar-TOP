<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Staff task workflow statuses.
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Done = 'done';

    /**
     * Human-readable task status label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Blocked => 'Blocked',
            self::Done => 'Done',
        };
    }

    /**
     * Bootstrap 5 badge modifier class.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Todo => 'bg-secondary',
            self::InProgress => 'bg-primary',
            self::Blocked => 'bg-danger',
            self::Done => 'bg-success',
        };
    }
}
