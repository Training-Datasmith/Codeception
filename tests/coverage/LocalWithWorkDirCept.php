<?php

declare(strict_types=1);

$I = new CoverGuy($scenario);
$I->wantTo('try generate local codecoverage with work directory');
$I->amInPath('tests/data/sandbox');
$I->executeCommand('run remote_server --env=work --coverage --debug');
$I->seeInShellOutput('Replacing all instances of /tmp/test/ with ' . realpath(__DIR__ . '/../..') . '/tests/data/sandbox/');
