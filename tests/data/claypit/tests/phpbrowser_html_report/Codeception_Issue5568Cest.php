<?php

declare(strict_types=1);

class CodeceptionIssue5568Cest
{
    public function failureShouldCreateHtmlSnapshot(AcceptanceTester $I)
    {
        $I->amOnPage('/');
        $I->see('SomethingThatIsNotThereToFailTheTest');
    }
}
