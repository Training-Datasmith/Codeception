<?php

declare (strict_types=1);
namespace Codeception;

interface Custom_Command_Interface
{
    /**
     * returns the name of the command
     */
    public static function get_command_name(): string;
}