<?php

declare (strict_types=1);
namespace Codeception\Command\Shared;

use Codeception\Scenario;
trait Actor_Trait
{
    protected function get_actor_class_name(): ?string
    {
        if (empty($this->settings['actor'])) {
            return null;
        }
        $namespace = '';
        if ($this->settings['namespace']) {
            $namespace .= '\\' . $this->settings['namespace'];
        }
        if (isset($this->settings['support_namespace'])) {
            $namespace .= '\\' . $this->settings['support_namespace'];
        }
        $namespace = rtrim($namespace, '\\') . '\\';
        return $namespace . $this->settings['actor'];
    }
    private function get_actor($test): ?object
    {
        $actor_class = $this->get_actor_class_name();
        return $actor_class ? new $actor_class(new Scenario($test)) : null;
    }
}