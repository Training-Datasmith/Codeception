<?php

declare(strict_types=1);

namespace Codeception\Lib\Interfaces;

use Doctrine\ORM\EntityManagerInterface;

interface DoctrineProvider
{
    public function _getEntityManager(): EntityManagerInterface;
}
