<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\ClassMove;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;
use MertBag\DomainMigrator\Migration\TargetResolver;

/**
 * Problems that keep a class from being moved at all.
 */
final readonly class ConflictDetector implements RiskDetector
{
    public function __construct(
        private TargetResolver $targets,
    ) {}

    public function detect(PlanContext $context): array
    {
        return array_values(array_filter([
            $this->unresolved($context),
            $this->pathMismatches($context),
            $this->sharedFiles($context),
            $this->existingTargets($context),
            $this->duplicateTargets($context),
        ]));
    }

    private function unresolved(PlanContext $context): ?MigrationRisk
    {
        $items = array_map(
            fn (string $class): RiskItem => new RiskItem($class, 'it or its target is not under a PSR-4 root', $context->graph->nodes[$class]->file ?? null),
            $context->unresolved,
        );

        return $this->risk(
            'Not autoloaded by PSR-4',
            'The target of these classes cannot be determined, because they or the domain namespace are not under a PSR-4 root in composer.json.',
            'Add a PSR-4 root to composer.json that covers both the class and the domain namespace.',
            $items,
        );
    }

    private function pathMismatches(PlanContext $context): ?MigrationRisk
    {
        $classesPerFile = $this->classesPerFile($context);
        $items = [];

        foreach ($context->moves as $move) {
            $expected = $this->targets->expectedPath($move->class, $context->roots);

            if ($expected !== null && $expected !== $move->file && count($classesPerFile[$move->file] ?? []) === 1) {
                $items[] = new RiskItem($move->class, "PSR-4 expects it in {$expected}", $move->file);
            }
        }

        return $this->risk(
            'File path does not match the namespace',
            'These classes are declared in a file whose path does not follow PSR-4, so the move cannot work out where the file belongs.',
            'Move each class to the file PSR-4 expects first, or fix its namespace.',
            $items,
        );
    }

    private function sharedFiles(PlanContext $context): ?MigrationRisk
    {
        $classesPerFile = $this->classesPerFile($context);
        $items = [];

        foreach ($context->moves as $move) {
            if (count($classesPerFile[$move->file] ?? []) > 1) {
                $others = array_values(array_diff($classesPerFile[$move->file], [$move->class]));
                $items[] = new RiskItem($move->class, 'shares its file with '.implode(', ', array_map(class_basename(...), $others)), $move->file);
            }
        }

        return $this->risk(
            'More than one class in a file',
            'A file can only move as a whole, and these files declare more than one class.',
            'Split each file into one file per class first.',
            $items,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function classesPerFile(PlanContext $context): array
    {
        $classesPerFile = [];

        foreach ($context->graph->nodes as $node) {
            $classesPerFile[$node->file][] = $node->name;
        }

        return $classesPerFile;
    }

    private function existingTargets(PlanContext $context): ?MigrationRisk
    {
        $items = [];

        foreach ($context->moves as $move) {
            if (is_file($context->basePath.'/'.$move->targetFile) || $context->exists($move->target)) {
                $items[] = new RiskItem($move->class, "{$move->target} already exists", $move->targetFile);
            }
        }

        return $this->risk(
            'Target already exists',
            'Moving these classes would overwrite an existing class or file.',
            'Rename or remove the existing class, or assign the class to the domain that already owns it.',
            $items,
        );
    }

    private function duplicateTargets(PlanContext $context): ?MigrationRisk
    {
        $byTarget = [];

        foreach ($context->moves as $move) {
            $byTarget[$move->target][] = $move;
        }

        $items = [];

        foreach ($byTarget as $target => $moves) {
            if (count($moves) > 1) {
                foreach ($moves as $move) {
                    $items[] = new RiskItem($move->class, "moves to {$target}, like ".implode(', ', array_map(
                        fn (ClassMove $other): string => $other->class,
                        array_values(array_filter($moves, fn (ClassMove $other): bool => $other !== $move)),
                    )), $move->file);
                }
            }
        }

        return $this->risk(
            'Two classes move to the same name',
            'Dropping the domain name from the namespace gives these classes the same target.',
            'Rename one of the classes first.',
            $items,
        );
    }

    /**
     * @param  list<RiskItem>  $items
     */
    private function risk(string $title, string $message, string $fix, array $items): ?MigrationRisk
    {
        return $items === [] ? null : new MigrationRisk(RiskType::Conflict, RiskLevel::Blocker, $title, $message, $fix, $items);
    }
}
