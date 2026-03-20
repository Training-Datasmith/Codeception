<?php

declare (strict_types=1);
namespace Codeception\Event;

use Codeception\Suite;
use Symfony\Contracts\Event_Dispatcher\Event;
class Suite_Event extends Event
{
    public function __construct(protected ?Suite $suite = null, protected array $settings = [])
    {
    }
    public function get_suite(): ?Suite
    {
        return $this->suite;
    }
    public function get_settings(): array
    {
        return $this->settings;
    }
}