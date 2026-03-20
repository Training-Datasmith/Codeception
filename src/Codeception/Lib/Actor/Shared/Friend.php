<?php

declare (strict_types=1);
namespace Codeception\Lib\Actor\Shared;

use Codeception\Lib\Friend as LibFriend;
use Codeception\Scenario;
trait Friend
{
    protected array $friends = [];
    abstract protected function get_scenario(): Scenario;
    public function have_friend(string $name, ?string $actor_class = null): Lib_Friend
    {
        if (!isset($this->friends[$name])) {
            $actor = $actor_class === null ? $this : new $actor_class($this->get_scenario());
            $this->friends[$name] = new Lib_Friend($name, $actor, $this->get_scenario()->current('modules'));
        }
        return $this->friends[$name];
    }
}