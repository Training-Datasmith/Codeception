<?php

declare(strict_types=1);

namespace CodeceptionPhpunit\Tests\Parser;

use Codeception\Lib\Parser;
use Codeception\Scenario;
use Codeception\Test\Cept;
use Codeception\Test\Metadata;
use CodeceptionPhpunit\Support\PhpunitTestCase;

final class ParserTest extends PhpunitTestCase
{
    public function testParseFeatureStripsBareStarBeforeBlockComment(): void
    {
        $cept = new Cept('demo', __FILE__);
        $parser = new Parser($cept->getScenario(), $cept->getMetadata());
        $code = <<<'PHP'
<?php
$I->seeFileFound('*.log');
$I->wantTo('see logs');
/* note */
PHP;
        $parser->parseFeature($code);
        $this->assertSame('see logs', $cept->getScenario()->getFeature());
    }

    public function testGetClassesFromFixtureFile(): void
    {
        $file = dirname(__DIR__) . '/fixtures/ParserSampleClass.php';
        $classes = Parser::getClassesFromFile($file);
        $this->assertContains('CodeceptionPhpunitFixtures\\ParserSampleClass', $classes);
    }
}
