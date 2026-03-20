<?php

declare (strict_types=1);
namespace Codeception\Lib\Interfaces;

interface Data_Mapper extends ORM, Doctrine_Provider
{
    public function have_in_repository(string $entity, array $data);
    public function see_in_repository(string $entity, array $params = []): void;
    public function dont_see_in_repository(string $entity, array $params = []): void;
    public function grab_from_repository(string $entity, string $field, array $params = []);
}