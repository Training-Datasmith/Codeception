<?php

declare(strict_types=1);

namespace Helper;

class Scenario extends \Codeception\Module
{
    public function throwException($message)
    {
        throw new \Exception($message);
    }
}
