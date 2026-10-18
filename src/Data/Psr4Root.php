<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class Psr4Root
{
    /**
     * @param  string  $namespace  With a trailing backslash, e.g. App\
     * @param  string  $directory  Relative to the base path, without a trailing slash, e.g. app
     */
    public function __construct(
        public string $namespace,
        public string $directory,
    ) {}

    public function contains(string $class): bool
    {
        return str_starts_with($class, $this->namespace);
    }

    public function pathOf(string $class): string
    {
        $relative = str_replace('\\', '/', substr($class, strlen($this->namespace)));

        return ($this->directory === '' ? '' : $this->directory.'/').$relative.'.php';
    }
}
