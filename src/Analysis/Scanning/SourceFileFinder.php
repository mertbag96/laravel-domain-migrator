<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Scanning;

use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\SourceFile;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use Symfony\Component\Finder\Finder;

final readonly class SourceFileFinder
{
    /**
     * @return list<SourceFile>
     *
     * @throws InvalidConfigurationException
     */
    public function find(AnalysisOptions $options): array
    {
        $files = [];

        foreach ($options->paths as $path) {
            $directory = str_starts_with($path, '/') ? $path : $options->basePath.'/'.$path;

            if (! is_dir($directory)) {
                throw InvalidConfigurationException::missingPath($path);
            }

            foreach (Finder::create()->files()->name('*.php')->in($directory) as $file) {
                $absolutePath = $file->getRealPath() ?: $file->getPathname();
                $relativePath = $this->relativePath($absolutePath, $options->basePath);

                if (! $this->isExcluded($relativePath, $options->exclude)) {
                    $files[$absolutePath] = new SourceFile($absolutePath, $relativePath);
                }
            }
        }

        ksort($files);

        return array_values($files);
    }

    private function relativePath(string $absolutePath, string $basePath): string
    {
        $absolutePath = str_replace('\\', '/', $absolutePath);
        $basePath = str_replace('\\', '/', realpath($basePath) ?: $basePath).'/';

        return str_starts_with($absolutePath, $basePath)
            ? substr($absolutePath, strlen($basePath))
            : $absolutePath;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function isExcluded(string $relativePath, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }
}
