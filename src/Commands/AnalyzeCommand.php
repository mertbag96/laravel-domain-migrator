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
use MertBag\DomainMigrator\Reporting\JsonReportFormatter;
use Symfony\Component\Console\Output\OutputInterface;

final class AnalyzeCommand extends Command
{
    /** @var string */
    protected $signature = 'domain:analyze
        {--format=console : The report format (console, json)}
        {--output= : Write the JSON report to this file instead of the terminal}';

    /** @var string */
    protected $description = 'Analyze class dependencies and coupling hot spots in the application';

    public function handle(
        AnalyzeApplicationAction $analyze,
        ConsoleReportRenderer $consoleRenderer,
        JsonReportFormatter $jsonFormatter,
        Filesystem $files,
    ): int {
        $format = ReportFormat::tryFrom($this->stringOption('format') ?? ReportFormat::Console->value);
        $outputPath = $this->stringOption('output');

        if ($format === null) {
            $this->components->error('Unsupported format. Use "console" or "json".');

            return self::FAILURE;
        }

        if ($outputPath !== null && $format !== ReportFormat::Json) {
            $this->components->error('The --output option requires --format=json.');

            return self::FAILURE;
        }

        try {
            $report = $analyze->execute($this->laravel->make(AnalysisOptions::class));
        } catch (InvalidConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($format === ReportFormat::Console) {
            $consoleRenderer->render($report, $this->output, $this->components);

            return self::SUCCESS;
        }

        $json = $jsonFormatter->format($report);

        if ($outputPath === null) {
            $this->output->writeln($json, OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $files->put($outputPath, $json.PHP_EOL);
        $this->components->info("Report written to [{$outputPath}].");

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
