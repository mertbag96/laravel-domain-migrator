<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Commands;

use Illuminate\Console\Command;
use MertBag\DomainMigrator\Actions\CheckBoundariesAction;
use MertBag\DomainMigrator\Boundaries\BaselineRepository;
use MertBag\DomainMigrator\Boundaries\DomainMapReader;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\Baseline;
use MertBag\DomainMigrator\Data\BoundaryOptions;
use MertBag\DomainMigrator\Enums\CheckFormat;
use MertBag\DomainMigrator\Exceptions\InvalidBaselineException;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Exceptions\InvalidDomainMapException;
use MertBag\DomainMigrator\Reporting\CheckConsoleRenderer;
use MertBag\DomainMigrator\Reporting\GithubAnnotationFormatter;
use MertBag\DomainMigrator\Reporting\JsonReportFormatter;
use Symfony\Component\Console\Output\OutputInterface;

final class CheckCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:check
        {--map= : The domain map to check against (default: boundaries.map, domain-map.json)}
        {--baseline= : Known violations that do not fail the check (default: boundaries.baseline, domain-baseline.json)}
        {--generate-baseline : Record every current violation in the baseline instead of failing}
        {--format=console : The output format (console, json, github)}';

    /** @var string */
    protected $description = 'Fail when a class depends on a domain that its own domain does not list in depends_on';

    public function handle(
        CheckBoundariesAction $check,
        DomainMapReader $mapReader,
        BaselineRepository $baselines,
        CheckConsoleRenderer $consoleRenderer,
        JsonReportFormatter $json,
        GithubAnnotationFormatter $github,
    ): int {
        $format = CheckFormat::tryFrom($this->stringOption('format') ?? CheckFormat::Console->value);

        if ($format === null) {
            $this->components->error('Unsupported format. Use "console", "json" or "github".');

            return self::INVALID;
        }

        try {
            $paths = $this->laravel->make(BoundaryOptions::class);
            $mapPath = $this->stringOption('map') ?? $paths->mapPath;
            $baselinePath = $this->stringOption('baseline') ?? $paths->baselinePath;
            $generateBaseline = (bool) $this->option('generate-baseline');

            $result = $check->execute(
                $this->laravel->make(AnalysisOptions::class),
                $mapReader->read($mapPath),
                $generateBaseline ? new Baseline : $baselines->read($baselinePath),
            );
        } catch (InvalidConfigurationException|InvalidDomainMapException|InvalidBaselineException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $displayMapPath = $this->relativeToBase($mapPath);

        if ($generateBaseline) {
            $baselines->write(Baseline::fromViolations($result->allViolations()), $baselinePath);

            $this->components->info(sprintf(
                'Baseline written to [%s] with %d %s. Commit it to version control.',
                $this->relativeToBase($baselinePath),
                count($result->allViolations()),
                count($result->allViolations()) === 1 ? 'violation' : 'violations',
            ));

            return self::SUCCESS;
        }

        match ($format) {
            CheckFormat::Console => $consoleRenderer->render($result, $displayMapPath, $this->output, $this->components),
            CheckFormat::Json => $this->output->writeln($json->formatCheckResult($result), OutputInterface::OUTPUT_RAW),
            CheckFormat::Github => $this->output->writeln($github->format($result, $displayMapPath), OutputInterface::OUTPUT_RAW),
        };

        return $result->passed() ? self::SUCCESS : self::FAILURE;
    }

    private function relativeToBase(string $path): string
    {
        $basePath = rtrim($this->laravel->basePath(), '/').'/';

        return str_starts_with($path, $basePath) ? substr($path, strlen($basePath)) : $path;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
