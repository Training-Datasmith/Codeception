<?php

declare (strict_types=1);
namespace Codeception\Lib\Interfaces;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
/**
 * If class implementing this interface is subscribed to event dispatcher
 * it replaces default Console Subscriber
 */
interface Console_Printer extends Event_Subscriber_Interface
{
}