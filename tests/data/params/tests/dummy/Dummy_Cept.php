<?php

declare(strict_types=1);

$I = new DummyTester($scenario);
$I->seePathIsSet();
$I->seeVarsAreSet();
