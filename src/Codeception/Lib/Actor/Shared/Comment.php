<?php

declare (strict_types=1);
namespace Codeception\Lib\Actor\Shared;

use Codeception\Scenario;
trait Comment
{
    abstract protected function get_scenario(): Scenario;
    public function expect_to(string $prediction): self
    {
        return $this->comment('I expect to ' . $prediction);
    }
    public function expect(string $prediction): self
    {
        return $this->comment('I expect ' . $prediction);
    }
    public function am_going_to(string $argumentation): self
    {
        return $this->comment('I am going to ' . $argumentation);
    }
    public function am(string $role): self
    {
        $role = trim($role);
        if (stripos('aeiou', $role[0]) !== false) {
            return $this->comment('As an ' . $role);
        }
        return $this->comment('As a ' . $role);
    }
    public function look_forward_to(string $achieve_value): self
    {
        return $this->comment('So that I ' . $achieve_value);
    }
    public function comment(string $description): self
    {
        $this->get_scenario()->comment($description);
        return $this;
    }
}