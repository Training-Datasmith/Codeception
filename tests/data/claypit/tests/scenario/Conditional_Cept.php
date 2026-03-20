<?php

declare(strict_types=1);

$I = new ScenarioGuy($scenario);
$I->amInPath('.');
$I->canSeeFileFound('not-a-file');
$I->canSeeFileFound('not-a-dir');
$I->canSeeFileFound('nothing');
