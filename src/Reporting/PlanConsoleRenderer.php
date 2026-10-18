<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use MertBag\DomainMigrator\Data\MigrationPlan;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\SkippedFile;
use MertBag\DomainMigrator\Enums\RiskLevel;

final readonly class PlanConsoleRenderer
{
    private const int LISTED_MOVES = 25;

    private const int LISTED_ITEMS = 10;

    public function render(MigrationPlan $plan, OutputStyle $output, Factory $components): void
    {
        $output->newLine();
        $output->writeln(sprintf(
            '  <options=bold>%s</> → <options=bold>%s</> <fg=gray>(%d %s move, %d %s refer to them)</>',
            $plan->domain,
            $plan->namespace,
            count($plan->moves),
            count($plan->moves) === 1 ? 'class' : 'classes',
            count($plan->referencingFiles()),
            count($plan->referencingFiles()) === 1 ? 'file' : 'files',
        ));
        $output->newLine();

        foreach (array_slice($plan->moves, 0, self::LISTED_MOVES) as $move) {
            $components->twoColumnDetail("<fg=gray>{$move->file}</>", $move->targetFile);
        }

        if (count($plan->moves) > self::LISTED_MOVES) {
            $output->writeln(sprintf('  <fg=gray>…and %d more. Use --format=json for the full plan.</>', count($plan->moves) - self::LISTED_MOVES));
        }

        if ($plan->inPlace !== []) {
            $output->writeln(sprintf('  <fg=gray>%d %s already in %s.</>', count($plan->inPlace), count($plan->inPlace) === 1 ? 'class is' : 'classes are', $plan->namespace));
        }

        foreach ($plan->risks as $risk) {
            $this->renderRisk($risk, $output, $components);
        }

        $output->newLine();
        $this->renderSummary($plan, $components);

        if ($plan->skippedFiles !== []) {
            $components->warn(sprintf('Skipped %d %s that could not be parsed:', count($plan->skippedFiles), count($plan->skippedFiles) === 1 ? 'file' : 'files'));
            $components->bulletList(array_map(fn (SkippedFile $file): string => "{$file->file} <fg=gray>({$file->reason})</>", $plan->skippedFiles));
        }
    }

    private function renderRisk(MigrationRisk $risk, OutputStyle $output, Factory $components): void
    {
        $label = match ($risk->level) {
            RiskLevel::Blocker => '<fg=white;bg=red;options=bold> BLOCKER </>',
            RiskLevel::Breaking => '<fg=black;bg=yellow;options=bold> BREAKING </>',
            RiskLevel::Review => '<fg=white;bg=blue;options=bold> REVIEW </>',
        };

        $output->newLine();
        $output->writeln("  {$label} <options=bold>{$risk->title}</>");
        $output->writeln("  {$risk->message}");

        foreach (array_slice($risk->items, 0, self::LISTED_ITEMS) as $item) {
            $components->twoColumnDetail(
                '<fg=gray>'.($item->location() ?? '').'</>',
                class_basename($item->class).' <fg=gray>'.$item->detail.'</>',
            );
        }

        if (count($risk->items) > self::LISTED_ITEMS) {
            $output->writeln(sprintf('  <fg=gray>…and %d more.</>', count($risk->items) - self::LISTED_ITEMS));
        }

        $output->writeln("  <fg=gray>Fix:</> {$risk->fix}");
    }

    private function renderSummary(MigrationPlan $plan, Factory $components): void
    {
        $blockers = $plan->count(RiskLevel::Blocker);
        $breaking = $plan->count(RiskLevel::Breaking);
        $review = $plan->count(RiskLevel::Review);

        $counts = sprintf(
            '%d %s, %d breaking %s and %d to review',
            $blockers,
            $blockers === 1 ? 'blocker' : 'blockers',
            $breaking,
            $breaking === 1 ? 'risk' : 'risks',
            $review,
        );

        if ($plan->moves === []) {
            $components->info("Nothing to move: every class of {$plan->domain} is already in {$plan->namespace}.");
        } elseif ($blockers > 0) {
            $components->error("{$counts}. Fix the blockers before moving {$plan->domain}. Nothing was changed.");
        } elseif ($breaking > 0) {
            $components->warn("{$counts}. Handle the breaking risks before deploying the move. Nothing was changed.");
        } else {
            $components->info("{$counts}. Nothing was changed.");
        }
    }
}
