<?php

declare (strict_types=1);
namespace Codeception\Step;

use Codeception\Util\Template;
interface Generated_Step
{
    public static function get_template(Template $template): ?Template;
}