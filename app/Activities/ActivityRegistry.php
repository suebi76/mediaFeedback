<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Activities;

class ActivityRegistry
{
    private array $activities = [];

    public function __construct()
    {
        foreach (glob(V2_APP . '/Activities/*Activity.php') ?: [] as $file) {
            $class = 'MediaFeedbackV2\\Activities\\' . basename($file, '.php');
            if (!class_exists($class)) {
                require_once $file;
            }
            if (!is_subclass_of($class, ActivityBase::class)) {
                continue;
            }
            $instance = new $class();
            $this->activities[$instance->getType()] = $instance;
        }
        ksort($this->activities);
    }

    public function all(): array
    {
        return $this->activities;
    }

    public function get(string $type): ?ActivityBase
    {
        return $this->activities[$type] ?? null;
    }
}