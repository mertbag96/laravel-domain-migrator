<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\ClassReference;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\ReferenceKind;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

final readonly class StringReferenceDetector implements RiskDetector
{
    public function detect(PlanContext $context): array
    {
        $items = array_values(array_map(
            fn (ClassReference $reference): RiskItem => new RiskItem($reference->class, $this->describe($reference->file), $reference->file, $reference->line),
            array_filter($context->project->references, fn (ClassReference $reference): bool => $reference->kind === ReferenceKind::String),
        ));

        if ($items === []) {
            return [];
        }

        return [new MigrationRisk(
            RiskType::StringReference,
            RiskLevel::Review,
            'Class names in strings',
            'Class names in strings, Blade templates and data files are not updated by a rename.',
            'Replace strings with ::class references where you can, and update the rest by hand after the move.',
            $items,
        )];
    }

    private function describe(string $file): string
    {
        return match (true) {
            str_ends_with($file, '.blade.php') => 'is used in a Blade template',
            str_ends_with($file, '.php') => 'is written as a string',
            default => 'is written in a data file',
        };
    }
}
