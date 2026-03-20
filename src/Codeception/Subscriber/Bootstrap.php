<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use Codeception\Configuration;
use Codeception\Event\Suite_Event;
use Codeception\Events;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Bootstrap implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::SUITE_INIT => 'loadBootstrap'];
    public function load_bootstrap(Suite_Event $event): void
    {
        $settings = $event->get_settings();
        $bootstrap = $settings['bootstrap'] ?? '';
        if ($bootstrap === '') {
            return;
        }
        Configuration::load_bootstrap($bootstrap, $settings['path']);
    }
}