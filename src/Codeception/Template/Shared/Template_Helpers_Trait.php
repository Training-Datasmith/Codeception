<?php

declare (strict_types=1);
namespace Codeception\Template\Shared;

trait Template_Helpers_Trait
{
    protected function create_suite_dirs(string $dir): void
    {
        $paths = ['_output', 'Support', 'Support/Data', 'Support/_generated'];
        foreach ($paths as $sub) {
            $full = $dir . DIRECTORY_SEPARATOR . $sub;
            if ($sub === 'Support/Data') {
                $this->create_empty_directory($full);
            } elseif (str_ends_with($sub, '_generated')) {
                $this->create_empty_directory($full);
                $this->git_ignore($full);
            } else {
                $this->create_directory_for($full);
                if ($sub === '_output') {
                    $this->git_ignore($full);
                }
            }
        }
    }
    /**
     * @param string[] $modules
     */
    protected function ensure_modules(array $modules): void
    {
        $to_install = [];
        foreach ($modules as $module) {
            $class = '\Codeception\Module\\' . $module;
            if (!class_exists($class)) {
                $to_install[] = $module;
            }
        }
        if ($to_install !== []) {
            $this->add_modules_to_composer($to_install);
        }
    }
}