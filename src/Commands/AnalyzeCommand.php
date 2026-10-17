<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use MertBag\DomainMigrator\Actions\AnalyzeApplicationAction;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Enums\ReportFormat;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Reporting\ConsoleReportRenderer;
use MertBag\DomainMigrator\Reporting\ReportWriter;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:analyze
        {--format=console : The report format (console, json, html)}
        {--output= : Write the report to this file (json defaults to the terminal, html to domain-report.html)}
        {--map= : Also write the suggested domains to this file, e.g. domain-map.json}
        {--force : Overwrite an existing domain map}';

    /** @var string */
    protected $description = 'Analyze class dependencies, coupling hot spots and candidate domains in the application';

    public function handle(
        AnalyzeApplicationAction $analyze,
        ConsoleReportRenderer $consoleRenderer,
        ReportWriter $writer,
        Filesystem $files,
    ): int {
        $format = ReportFormat::tryFrom($this->stringOption('format') ?? ReportFormat::Console->value);

        if ($format === null) {
            $this->components->error('Unsupported format. Use "console", "json" or "html".');

            return self::FAILURE;
        }

        $outputPath = $this->stringOption('output');

        if ($outputPath !== null && $format === ReportFormat::Console) {
            $this->components->error('The --output option requires --format=json or --format=html.');

            return self::FAILURE;
        }

        $outputPath ??= $format->defaultOutputPath();
        $mapPath = $this->stringOption('map');

        if ($mapPath !== null && $files->exists($mapPath) && ! $this->option('force')) {
            $this->components->error("The domain map [{$mapPath}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        try {
            $report = $analyze->execute($this->laravel->make(AnalysisOptions::class));
        } catch (InvalidConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($mapPath !== null) {
            $writer->writeDomainMap($report->domains, $mapPath);
        }

        if ($format === ReportFormat::Console) {
            $consoleRenderer->render($report, $this->output, $this->components);
        } elseif ($outputPath === null) {
            $this->output->writeln($writer->render($report, $format), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        } else {
            $writer->write($report, $format, $outputPath);
            $this->components->info("Report written to [{$outputPath}].");
        }

        if ($mapPath !== null) {
            $this->components->info("Domain map written to [{$mapPath}]. Review it and commit it to version control.");
        }

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
