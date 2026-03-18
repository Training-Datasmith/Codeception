<?php

declare(strict_types=1);

namespace Codeception\Step;

use Codeception\Util\Template;

interface GeneratedStep
{
    public static function getTemplate(Template $template): ?Template;
}
