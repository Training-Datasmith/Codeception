<?php

declare(strict_types=1);

$I = new CoverGuy($scenario);
$I->wantTo('try generate remote codecoverage phpunit report');
$I->amInPath('tests/data/sandbox');
$I->executeCommand('run remote --coverage-phpunit');
$I->seeFileFound('index.xml', 'tests/_output/coverage-phpunit');
$I->seeCoverageStatsNotEmpty();
