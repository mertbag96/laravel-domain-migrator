<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Pest;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Container\Container;
use MertBag\DomainMigrator\Actions\CheckBoundariesAction;
use MertBag\DomainMigrator\Boundaries\BaselineRepository;
use MertBag\DomainMigrator\Boundaries\DomainMapReader;
use MertBag\DomainMigrator\Data\AnalysisOptions;
use MertBag\DomainMigrator\Data\BoundaryCheckResult;
use MertBag\DomainMigrator\Data\BoundaryOptions;
use MertBag\DomainMigrator\Exceptions\InvalidConfigurationException;
use MertBag\DomainMigrator\Reporting\CheckConsoleRenderer;
use Pest\Mixins\Expectation;
use Pest\TestSuite;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The "domains" arch preset: fails on the same boundary violations as domain:check.
 */
final readonly class DomainsPreset
{
    /**
     * @param  string|null  $basePath  The project root, used when the Laravel application is not booted (defaults to Pest's root path)
     */
    public function __construct(
        private ?string $basePath = null,
    ) {}

    /**
     * @return list<Expectation<bool|null>>
     */
    public function __invoke(): array
    {
        $container = $this->container();
        $paths = $container->make(BoundaryOptions::class);
        $options = $container->make(AnalysisOptions::class);

        $result = $container->make(CheckBoundariesAction::class)->execute(
            $options,
            $container->make(DomainMapReader::class)->read($paths->mapPath),
            $container->make(BaselineRepository::class)->read($paths->baselinePath),
        );

        $mapPath = $this->relativeTo($paths->mapPath, $options->basePath);

        return [
            expect($result->outdatedMap)->toBeFalse("The domain map [{$mapPath}] was written by an older version and has no depends_on rules. Run php artisan domain:analyze --update --map={$mapPath} to add today's dependencies as rules."),
            expect($result->passed())->toBeTrue($this->report($result, $mapPath)),
        ];
    }

    private function container(): Container
    {
        $application = Container::getInstance();

        if ($application->bound(BoundaryOptions::class)) {
            return $application;
        }

        $basePath = $this->basePath ?? TestSuite::getInstance()->rootPath;
        $config = $this->config($basePath);

        $container = new Container;
        $container->instance(AnalysisOptions::class, AnalysisOptions::fromConfig($config, $basePath));
        $container->instance(BoundaryOptions::class, BoundaryOptions::fromConfig($config, $basePath));

        return $container;
    }

    /**
     * The package configuration merged with the application's, the same way Laravel merges it.
     *
     * @return array<mixed>
     */
    private function config(string $basePath): array
    {
        $config = require dirname(__DIR__, 2).'/config/domain-migrator.php';
        $published = $basePath.'/config/domain-migrator.php';
        $overrides = is_file($published) ? require $published : [];

        if (! is_array($config) || ! is_array($overrides)) {
            throw InvalidConfigurationException::notAnArray();
        }

        return array_merge($config, $overrides);
    }

    private function report(BoundaryCheckResult $result, string $mapPath): string
    {
        $buffer = new BufferedOutput;
        $output = new OutputStyle(new ArrayInput([]), $buffer);

        (new CheckConsoleRenderer)->render($result, $mapPath, $output, new Factory($output));

        return trim($buffer->fetch());
    }

    private function relativeTo(string $path, string $basePath): string
    {
        $basePath = rtrim($basePath, '/').'/';

        return str_starts_with($path, $basePath) ? substr($path, strlen($basePath)) : $path;
    }
}
