<?php

declare(strict_types=1);

$I = new UnitTester($scenario);
$I->wantTo('see that this test was not executed');
throw new \RuntimeException('in cept');
