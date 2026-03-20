<?php

declare (strict_types=1);
namespace Codeception\Lib\Interfaces;

interface Active_Record extends ORM
{
    public function have_record(string $model, array $attributes = []);
    public function see_record(string $model, array $attributes = []): void;
    public function dont_see_record(string $model, array $attributes = []): void;
    public function grab_record(string $model, array $attributes = []);
}