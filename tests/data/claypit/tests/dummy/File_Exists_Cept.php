<?php

declare(strict_types=1);

$I = new DumbGuy($scenario);
$I->wantTo('check config exists');
$codeception = 'codeception.yml';
$I->seeFileFound($codeception);
