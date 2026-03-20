<?php

declare(strict_types=1);

namespace Codeception\Test\Loader;

interface LoaderInterface
{
    public function loadTests(string $filename): void;

    public function getTests(): array;

    public function getPattern(): string;
}
