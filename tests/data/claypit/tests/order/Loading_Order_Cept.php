<?php

declare(strict_types=1);

// @group simple
\Codeception\Module\OrderHelper::appendToFile('S');
$I = new OrderGuy($scenario);
$I->wantTo('write a marker to file to determine loading order');
$I->appendToFile('T');
