<?php

declare(strict_types=1);

$I = new ScenarioGuy($scenario);
$I->wantTo('run scenario substeps');
$I->amInPath('.');
$I->seeCodeCoverageFilesArePresent();
