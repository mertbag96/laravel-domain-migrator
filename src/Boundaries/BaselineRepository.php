<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Boundaries;

use Illuminate\Filesystem\Filesystem;
use JsonException;
use MertBag\DomainMigrator\Data\Baseline;
use MertBag\DomainMigrator\Data\BaselineEntry;
use MertBag\DomainMigrator\Exceptions\InvalidBaselineException;

final readonly class BaselineRepository
{
    private const int FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public function __construct(
        private Filesystem $files,
    ) {}

    /**
     * A missing baseline file is an empty baseline.
     *
     * @throws InvalidBaselineException
     */
    public function read(string $path): Baseline
    {
        if (! $this->files->isFile($path)) {
            return new Baseline;
        }

        try {
            $data = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidBaselineException::invalidJson($path, $exception);
        }

        $violations = is_array($data) && ($data['schema'] ?? null) === Baseline::SCHEMA_VERSION ? ($data['violations'] ?? null) : null;

        if (! is_array($violations) || ! array_is_list($violations)) {
            throw InvalidBaselineException::invalidFormat($path);
        }

        $entries = [];

        foreach ($violations as $violation) {
            $source = is_array($violation) ? ($violation['source'] ?? null) : null;
            $target = is_array($violation) ? ($violation['target'] ?? null) : null;

            if (! is_string($source) || ! is_string($target)) {
                throw InvalidBaselineException::invalidFormat($path);
            }

            $entries[] = new BaselineEntry($source, $target);
        }

        return new Baseline($entries);
    }

    public function write(Baseline $baseline, string $path): void
    {
        $this->files->put($path, json_encode($baseline->toArray(), self::FLAGS).PHP_EOL);
    }
}
