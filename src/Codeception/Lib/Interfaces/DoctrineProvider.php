<?php

declare (strict_types=1);
namespace Codeception\Lib\Interfaces;

use Doctrine\ORM\Entity_Manager_Interface;
interface Doctrine_Provider
{
    public function _get_entity_manager(): Entity_Manager_Interface;
}