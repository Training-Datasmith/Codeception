<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Event\Test_Event;
abstract class Group_Object extends Extension
{
    public static $group;
    public function _before(Test_Event $event)
    {
    }
    public function _after(Test_Event $event)
    {
    }
    public static function get_subscribed_events(): array
    {
        $group_events = static::$group ? [Events::TEST_BEFORE . '.' . static::$group => '_before', Events::TEST_AFTER . '.' . static::$group => '_after'] : [];
        return array_merge($group_events, parent::get_subscribed_events());
    }
}