<?php

declare(strict_types=1);

$I = new AbsolutelyOtherGuy($scenario);
$I->wantTo('show message');
$I->amOnPage('/');
$I->see('Welcome to test app');
