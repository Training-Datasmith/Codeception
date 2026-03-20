<?php

declare(strict_types=1);

$I = new ScenarioGuy($scenario);
$I->wantTo('check that suite config is available');
$I->amInPath('.');
$I->seeFileFound('scenario.suite.yml');
