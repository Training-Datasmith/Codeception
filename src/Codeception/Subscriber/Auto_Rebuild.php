<?php

declare (strict_types=1);
namespace Codeception\Subscriber;

use function codecept_debug;
use Codeception\Configuration;
use Codeception\Event\Suite_Event;
use Codeception\Events;
use Codeception\Lib\Generator\Actions;
use function fclose;
use function fgets;
use function file_exists;
use function file_put_contents;
use function fopen;
use function is_writable;
use function mkdir;
use function preg_match;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
class Auto_Rebuild implements Event_Subscriber_Interface
{
    use Shared\Static_Events_Trait;
    /**
     * @var array<string, string>
     */
    protected static array $events = [Events::SUITE_INIT => 'updateActor'];
    public function update_actor(Suite_Event $event): void
    {
        $settings = $event->get_settings();
        if (!$settings['actor']) {
            codecept_debug('actor is empty');
            return;
        }
        $actor_actions_file = Configuration::support_dir() . '_generated' . DIRECTORY_SEPARATOR . $settings['actor'] . 'Actions.php';
        if (!file_exists($actor_actions_file)) {
            codecept_debug("Generating {$settings['actor']}Actions...");
            $this->generate_actor_actions($actor_actions_file, $settings);
            return;
        }
        $handle = @fopen($actor_actions_file, 'r');
        if ($handle && is_writable($actor_actions_file)) {
            $line = @fgets($handle);
            if (preg_match('#\[STAMP] ([a-f0-9]*)#', $line, $matches)) {
                $current_hash = Actions::gen_hash($event->get_suite()->get_modules(), $settings);
                if ($matches[1] !== $current_hash) {
                    codecept_debug("Rebuilding {$settings['actor']}...");
                    @fclose($handle);
                    $this->generate_actor_actions($actor_actions_file, $settings);
                    return;
                }
            }
            @fclose($handle);
        }
    }
    protected function generate_actor_actions(string $actor_actions_file, array $settings): void
    {
        $dir = Configuration::support_dir() . '_generated';
        if (!file_exists($dir)) {
            @mkdir($dir);
        }
        $generated = (new Actions($settings))->produce();
        @file_put_contents($actor_actions_file, $generated);
    }
}