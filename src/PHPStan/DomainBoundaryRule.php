<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\PHPStan;

use Illuminate\Filesystem\Filesystem;
use MertBag\DomainMigrator\Analysis\Scanning\ClassScanner;
use MertBag\DomainMigrator\Boundaries\BaselineRepository;
use MertBag\DomainMigrator\Boundaries\BoundaryChecker;
use MertBag\DomainMigrator\Boundaries\DomainMapReader;
use MertBag\DomainMigrator\Data\Baseline;
use MertBag\DomainMigrator\Data\BoundaryMap;
use MertBag\DomainMigrator\Data\SourceFile;
use MertBag\DomainMigrator\Data\Violation;
use MertBag\DomainMigrator\Exceptions\UnparsableFileException;
use PhpParser\Node;
use PHPStan\Analyser\DependencyTracker;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports the same violations as domain:check, inline in PHPStan. Does nothing until the domain map exists.
 * On PHPStan 2.3+ the map and baseline are tracked, so editing them re-analyses files from the result cache.
 *
 * @implements Rule<FileNode>
 */
final class DomainBoundaryRule implements Rule
{
    public const string IDENTIFIER = 'domainMigrator.boundary';

    private readonly ClassScanner $scanner;

    private readonly BoundaryChecker $checker;

    private bool $loaded = false;

    private ?BoundaryMap $map = null;

    private Baseline $baseline;

    public function __construct(
        private readonly string $mapPath,
        private readonly ?string $baselinePath = null,
    ) {
        $this->scanner = new ClassScanner;
        $this->checker = new BoundaryChecker;
        $this->baseline = new Baseline;
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($scope instanceof DependencyTracker) { // @phpstan-ignore instanceof.alwaysTrue (PHPStan < 2.3 has no DependencyTracker)
            $scope->trackFileDependency($this->mapPath);

            if ($this->baselinePath !== null) {
                $scope->trackFileDependency($this->baselinePath);
            }
        }

        $map = $this->map();

        if ($map === null) {
            return [];
        }

        try {
            $classes = $this->scanner->scan(new SourceFile($scope->getFile(), $scope->getFile()));
        } catch (UnparsableFileException) {
            return [];
        }

        $errors = [];

        foreach ($classes as $class) {
            foreach ($this->checker->checkClass($class, $map) as $violation) {
                if (! $this->baseline->contains($violation)) {
                    $errors[] = $this->error($violation);
                }
            }
        }

        return $errors;
    }

    private function error(Violation $violation): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf(
            '%s (%s) depends on %s (%s), but %s does not list %s in depends_on.',
            $violation->source,
            $violation->sourceDomain,
            $violation->target,
            $violation->targetDomain,
            $violation->sourceDomain,
            $violation->targetDomain,
        ))
            ->identifier(self::IDENTIFIER)
            ->line($violation->line ?? 1)
            ->tip(sprintf('Add %s to depends_on in %s if the dependency is intended.', $violation->targetDomain, basename($this->mapPath)))
            ->build();
    }

    private function map(): ?BoundaryMap
    {
        if ($this->loaded) {
            return $this->map;
        }

        $this->loaded = true;
        $files = new Filesystem;

        if (! $files->isFile($this->mapPath)) {
            return null;
        }

        $this->map = (new DomainMapReader($files))->read($this->mapPath);

        if ($this->baselinePath !== null) {
            $this->baseline = (new BaselineRepository($files))->read($this->baselinePath);
        }

        return $this->map;
    }
}
