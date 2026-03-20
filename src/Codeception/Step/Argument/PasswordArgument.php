<?php

declare (strict_types=1);
namespace Codeception\Step\Argument;

use Stringable;
class Password_Argument implements Formatted_Output, Stringable
{
    public function __construct(private readonly string $password)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_output(): string
    {
        return '******';
    }
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->password;
    }
}