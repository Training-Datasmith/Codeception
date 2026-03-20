<?php

declare (strict_types=1);
declare (ticks=1);
namespace Codeception\Subscriber;

use Codeception\Event\Suite_Event;
use Codeception\Events;
use Codeception\Result_Aggregator;
use function function_exists;
use function pcntl_async_signals;
use function pcntl_signal;
use RuntimeException;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Graceful_Termination implements Event_Subscriber_Interface
{
    /**
     * @var string
     */
    public const SIGNAL_FUNC = 'pcntl_signal';
    /**
     * @var string
     */
    public const ASYNC_SIGNAL_HANDLING_FUNC = 'pcntl_async_signals';
    public function __construct(private readonly Result_Aggregator $result_aggregator)
    {
    }
    public function handle_suite(Suite_Event $event): void
    {
        if (function_exists(self::ASYNC_SIGNAL_HANDLING_FUNC)) {
            pcntl_async_signals(true);
        }
        if (function_exists(self::SIGNAL_FUNC)) {
            pcntl_signal(SIGTERM, $this->terminate(...));
            pcntl_signal(SIGINT, $this->terminate(...));
        }
    }
    public function terminate(): void
    {
        $this->result_aggregator->stop();
        throw new RuntimeException("\n\n---------------------------\nTESTS EXECUTION TERMINATED\n---------------------------\n");
    }
    /**
     * @return array<string, string>
     */
    public static function get_subscribed_events(): array
    {
        if (!function_exists(self::SIGNAL_FUNC)) {
            return [];
        }
        return [Events::SUITE_BEFORE => 'handleSuite'];
    }
}