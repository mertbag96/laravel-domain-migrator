<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

final readonly class ReportApplication
{
    public function __construct(
        public string $name,
        public string $basePath,
    ) {}

    public static function fromProjectRoot(string $basePath): self
    {
        $resolved = realpath($basePath) ?: $basePath;
        $name = basename($resolved);

        $appConfig = $resolved.'/config/app.php';

        if (is_file($appConfig)) {
            $config = require $appConfig;

            if (is_array($config) && isset($config['name']) && is_string($config['name']) && $config['name'] !== '') {
                $name = $config['name'];
            }
        }

        return new self($name, $resolved);
    }

    /**
     * @return array{name: string, base_path: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'base_path' => $this->basePath,
        ];
    }
}
