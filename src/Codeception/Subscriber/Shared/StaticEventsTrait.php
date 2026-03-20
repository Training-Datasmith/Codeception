<?php

declare (strict_types=1);
namespace Codeception\Subscriber\Shared;

trait Static_Events_Trait
{
    public static function get_subscribed_events(): array
    {
        return static::$events;
    }
}