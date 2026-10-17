<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Reporting;

use MertBag\DomainMigrator\Data\BoundaryCheckResult;
use MertBag\DomainMigrator\Data\Violation;

/**
 * GitHub Actions workflow commands, which show violations inline on pull requests.
 *
 * @see https://docs.github.com/en/actions/reference/workflow-commands-for-github-actions
 */
final readonly class GithubAnnotationFormatter
{
    public function format(BoundaryCheckResult $result, string $mapPath): string
    {
        $lines = array_map(fn (Violation $violation): string => sprintf(
            '::error file=%s,line=%d,title=%s::%s',
            $this->property($violation->file),
            $violation->line ?? 1,
            $this->property("Domain boundary: {$violation->sourceDomain} → {$violation->targetDomain}"),
            $this->message(sprintf(
                '%s uses %s, but %s does not list %s in depends_on in %s.',
                $violation->source,
                $violation->target,
                $violation->sourceDomain,
                $violation->targetDomain,
                $mapPath,
            )),
        ), $result->violations);

        if ($result->outdatedMap) {
            $lines[] = '::warning::'.$this->message("The domain map {$mapPath} has no depends_on rules, so nothing was checked. Run php artisan domain:analyze --update --map={$mapPath} to add them.");
        }

        if ($result->fixedEntries !== []) {
            $lines[] = '::notice::'.$this->message(sprintf(
                '%d baseline %s. Run php artisan domain:check --generate-baseline to remove them.',
                count($result->fixedEntries),
                count($result->fixedEntries) === 1 ? 'entry no longer occurs' : 'entries no longer occur',
            ));
        }

        return implode(PHP_EOL, $lines);
    }

    private function message(string $value): string
    {
        return strtr($value, ['%' => '%25', "\r" => '%0D', "\n" => '%0A']);
    }

    private function property(string $value): string
    {
        return strtr($this->message($value), [':' => '%3A', ',' => '%2C']);
    }
}
