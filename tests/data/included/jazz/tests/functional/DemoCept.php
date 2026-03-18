<?php

declare(strict_types=1);

use Jazz\TestGuy;

$I = new TestGuy($scenario);
$I->wantTo('check that jazz musicians can add numbers');
$I->seeEquals(10, Jazz\Musician::add(3, 7));
