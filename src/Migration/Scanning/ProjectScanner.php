<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Scanning;

use MertBag\DomainMigrator\Data\ClassReference;
use MertBag\DomainMigrator\Data\ProjectScan;
use MertBag\DomainMigrator\Data\SkippedFile;
use MertBag\DomainMigrator\Enums\ReferenceKind;
use MertBag\DomainMigrator\Support\ProjectRelativePath;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Scans PHP files, Blade templates and JSON and YAML files for references to the moved classes.
 * PHP files are parsed; the others are searched as text, since a rename cannot update them.
 */
final readonly class ProjectScanner
{
    private const array TEXT_FILES = ['*.blade.php', '*.json', '*.yml', '*.yaml'];

    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    /**
     * @param  list<string>  $paths  Relative to the base path; missing directories are skipped.
     * @param  list<string>  $moved  The classes that move.
     */
    public function scan(string $basePath, array $paths, array $moved): ProjectScan
    {
        $basePath = rtrim($basePath, '/');
        $movedIndex = array_fill_keys($moved, true);
        $matcher = new ClassNameMatcher($moved);
        $references = [];
        $facts = [];
        $morphMap = null;
        $skippedFiles = [];

        foreach ($this->files($basePath, $paths) as $relativePath => $file) {
            if ($this->isTextFile($file)) {
                array_push($references, ...$this->textReferences($file->getContents(), $relativePath, $matcher));

                continue;
            }

            try {
                $statements = $this->parser->parse($file->getContents()) ?? [];
            } catch (Error $error) {
                $skippedFiles[] = new SkippedFile($relativePath, $error->getMessage());

                continue;
            }

            $visitor = new ProjectVisitor($relativePath, $movedIndex, $matcher);
            (new NodeTraverser($visitor))->traverse((new NodeTraverser(new NameResolver))->traverse($statements));

            array_push($references, ...$visitor->references());

            foreach ($visitor->facts() as $classFacts) {
                $facts[$classFacts->name] ??= $classFacts;
            }

            if ($visitor->morphMap() !== null) {
                $morphMap = [...$morphMap ?? [], ...$visitor->morphMap()];
            }
        }

        ksort($facts);

        return new ProjectScan($references, $facts, $morphMap === null ? null : array_values(array_unique($morphMap)), $skippedFiles);
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, SplFileInfo> Keyed by path relative to the base path, sorted.
     */
    private function files(string $basePath, array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            $directory = ProjectRelativePath::toAbsolute($path, $basePath);

            if (! is_dir($directory)) {
                continue;
            }

            $finder = Finder::create()->files()->in($directory)->exclude(['vendor', 'node_modules'])->name(['*.php', ...self::TEXT_FILES]);

            foreach ($finder as $file) {
                $absolutePath = $file->getRealPath() ?: $file->getPathname();
                $files[$this->relativePath($absolutePath, $basePath)] = $file;
            }
        }

        ksort($files);

        return $files;
    }

    private function isTextFile(SplFileInfo $file): bool
    {
        foreach (self::TEXT_FILES as $pattern) {
            if (fnmatch($pattern, $file->getFilename())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ClassReference>
     */
    private function textReferences(string $contents, string $file, ClassNameMatcher $matcher): array
    {
        $references = [];

        foreach ($matcher->matchWithOffsets($contents) as [$class, $offset]) {
            $line = substr_count($contents, "\n", 0, $offset) + 1;
            $references["{$class}:{$line}"] ??= new ClassReference($class, $file, $line, ReferenceKind::String);
        }

        return array_values($references);
    }

    private function relativePath(string $absolutePath, string $basePath): string
    {
        return ProjectRelativePath::toRelative($absolutePath, $basePath);
    }
}
