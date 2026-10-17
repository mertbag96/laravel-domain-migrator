<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use MertBag\DomainMigrator\Data\BoundaryCheckResult;
use MertBag\DomainMigrator\Data\SkippedFile;
use MertBag\DomainMigrator\Data\Violation;
use MertBag\DomainMigrator\Enums\DependencyType;

final readonly class CheckConsoleRenderer
{
    private const int UNCHECKED_LISTED = 5;

    public function render(BoundaryCheckResult $result, string $mapPath, OutputStyle $output, Factory $components): void
    {
        foreach ($this->groupByDomains($result->violations) as $group => $violations) {
            $output->writeln("  <options=bold>{$group}</> <fg=gray>(not in depends_on)</>");

            foreach ($violations as $violation) {
                $components->twoColumnDetail(
                    "<fg=gray>{$violation->file}".($violation->line === null ? '' : ":{$violation->line}").'</>',
                    sprintf(
                        '%s <fg=gray>uses</> %s <fg=gray>(%s)</>',
                        class_basename($violation->source),
                        class_basename($violation->target),
                        implode(', ', array_map(fn (DependencyType $type): string => str_replace('_', ' ', $type->value), $violation->types)),
                    ),
                );
            }

            $output->newLine();
        }

        $baselined = count($result->baselined) > 0 ? sprintf(' %d known %s in the baseline.', count($result->baselined), count($result->baselined) === 1 ? 'violation is' : 'violations are') : '';

        if ($result->passed()) {
            $components->info("No new boundary violations in {$result->classCount} classes.{$baselined}");
        } else {
            $components->error(sprintf(
                '%d new boundary %s.%s If a dependency is intended, add the domain to depends_on in [%s]; otherwise remove it.',
                count($result->violations),
                count($result->violations) === 1 ? 'violation' : 'violations',
                $baselined,
                $mapPath,
            ));
        }

        if ($result->fixedEntries !== []) {
            $components->warn(sprintf(
                '%d baseline %s. Run php artisan domain:check --generate-baseline to remove them.',
                count($result->fixedEntries),
                count($result->fixedEntries) === 1 ? 'entry no longer occurs' : 'entries no longer occur',
            ));
        }

        if ($result->outdatedMap) {
            $components->warn("The domain map [{$mapPath}] was written by an older version and has no depends_on rules, so nothing was checked. Run php artisan domain:analyze --update --map={$mapPath} to add today's dependencies as rules.");
        } elseif ($result->uncheckedDomains !== []) {
            $components->warn(sprintf(
                'Not checked because they have no depends_on: %s.',
                implode(', ', array_slice($result->uncheckedDomains, 0, self::UNCHECKED_LISTED))
                    .(count($result->uncheckedDomains) > self::UNCHECKED_LISTED ? sprintf(' and %d more', count($result->uncheckedDomains) - self::UNCHECKED_LISTED) : ''),
            ));
        }

        if ($result->unmappedClasses !== []) {
            $components->warn(sprintf(
                '%d %s not in the domain map and %s not checked. Run php artisan domain:analyze --update --map=%s to place %s.',
                count($result->unmappedClasses),
                count($result->unmappedClasses) === 1 ? 'class is' : 'classes are',
                count($result->unmappedClasses) === 1 ? 'is' : 'are',
                $mapPath,
                count($result->unmappedClasses) === 1 ? 'it' : 'them',
            ));
        }

        if ($result->skippedFiles !== []) {
            $components->warn(sprintf('Skipped %d %s that could not be parsed:', count($result->skippedFiles), count($result->skippedFiles) === 1 ? 'file' : 'files'));
            $components->bulletList(array_map(fn (SkippedFile $file): string => "{$file->file} <fg=gray>({$file->reason})</>", $result->skippedFiles));
        }
    }

    /**
     * @param  list<Violation>  $violations
     * @return array<string, list<Violation>>
     */
    private function groupByDomains(array $violations): array
    {
        $groups = [];

        foreach ($violations as $violation) {
            $groups["{$violation->sourceDomain} → {$violation->targetDomain}"][] = $violation;
        }

        ksort($groups);

        return $groups;
    }
}
