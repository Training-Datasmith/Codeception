<?php

declare(strict_types=1);

namespace Helper;

class Unit extends \Codeception\Module
{
    public function _before(\Codeception\TestInterface $test)
    {
        throw new \Exception('in before');
    }
}
