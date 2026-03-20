<?php

declare (strict_types=1);
namespace Codeception\Command\Shared;

use Symfony\Component\Console\Formatter\Output_Formatter_Style;
use Symfony\Component\Console\Output\Output_Interface;
trait Style_Trait
{
    public function add_styles(Output_Interface $output): void
    {
        $output->get_formatter()->set_style('notice', new Output_Formatter_Style('white', 'green', ['bold']));
        $output->get_formatter()->set_style('bold', new Output_Formatter_Style(null, null, ['bold']));
        $output->get_formatter()->set_style('warning', new Output_Formatter_Style(null, 'yellow', ['bold']));
        $output->get_formatter()->set_style('debug', new Output_Formatter_Style('cyan'));
    }
}