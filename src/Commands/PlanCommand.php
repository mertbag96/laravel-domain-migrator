<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Commands;

use Illuminate\Console\Command;
use MertBag\DomainMigrator\Actions\PlanMigrationAction;
use MertBag\DomainMigrator\Boundaries\DomainMapReader;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryOptions;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Enums\PlanFormat;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\InvalidDomainMapException;
use MertBag\DomainMigrator\Exceptions\InvalidMigrationPlanException;
use MertBag\DomainMigrator\Reporting\JsonReportFormatter;
use MertBag\DomainMigrator\Reporting\PlanConsoleRenderer;
use Symfony\Component\Console\Output\OutputInterface;

final class PlanCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:plan
        {domain : The domain from the domain map to plan the move for}
        {--map= : The domain map (default: boundaries.map, domain-map.json)}
        {--format=console : The output format (console, json)}';

    /** @var string */
    protected $description = 'Show which classes moving a domain would move and what it could break, without changing anything';

    public function handle(
        PlanMigrationAction $plan,
        DomainMapReader $mapReader,
        PlanConsoleRenderer $consoleRenderer,
        JsonReportFormatter $json,
    ): int {
        $format = PlanFormat::tryFrom($this->stringOption('format') ?? PlanFormat::Console->value);

        if ($format === null) {
            $this->components->error('Unsupported format. Use "console" or "json".');

            return self::INVALID;
        }

        try {
            $mapPath = $this->stringOption('map') ?? $this->laravel->make(BoundaryOptions::class)->mapPath;

            $result = $plan->execute(
                $this->laravel->make(AnalysisOptions::class),
                $this->laravel->make(MigrationOptions::class),
                $mapReader->read($mapPath),
                $this->stringArgument('domain'),
            );
        } catch (InvalidConfigurationException|InvalidDomainMapException|InvalidMigrationPlanException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        match ($format) {
            PlanFormat::Console => $consoleRenderer->render($result, $this->output, $this->components),
            PlanFormat::Json => $this->output->writeln($json->formatPlan($result), OutputInterface::OUTPUT_RAW),
        };

        return self::SUCCESS;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
