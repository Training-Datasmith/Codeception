<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use Codeception\Event\Suite_Event;
use Codeception\Events;
use Codeception\Lib\Console\Output;
use Codeception\Lib\Notification;
use Codeception\Subscriber\Shared\Static_Events_Trait;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Deprecation implements Event_Subscriber_Interface
{
    use Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::SUITE_AFTER => 'afterSuite'];
    private Output $output;
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options)
    {
        $this->output = new Output($options);
    }
    public function after_suite(Suite_Event $event): void
    {
        $messages = Notification::all();
        if ($messages === []) {
            return;
        }
        foreach (array_count_values($messages) as $msg => $count) {
            $msg = $count > 1 ? "{$count}x {$msg}" : $msg;
            $this->output->notification($msg);
        }
        $this->output->writeln('');
    }
}