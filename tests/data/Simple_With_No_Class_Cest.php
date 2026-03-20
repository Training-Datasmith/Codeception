<?php

declare(strict_types=1);

class SimpleWithNoClassCest
{
    public function phpFunctions(CodeGuy $I)
    {
        $I->execute(fn () => strtoupper('hello'));
        $I->seeResultEquals('HELLO');
    }
}
