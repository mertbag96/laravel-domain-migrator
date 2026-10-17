<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Metrics;

use MertBag\DomainMigrator\Analysis\Graph\DependencyGraph;
use MertBag\DomainMigrator\Data\ClassMetrics;

final readonly class MetricsCalculator
{
    /**
     * @return array<string, ClassMetrics>
     */
    public function calculate(DependencyGraph $graph): array
    {
        $metrics = [];

        foreach ($graph->nodes as $name => $node) {
            $afferent = count($graph->dependentsOf($name));
            $efferent = count($graph->dependenciesOf($name));
            $total = $afferent + $efferent;

            $metrics[$name] = new ClassMetrics(
                class: $name,
                kind: $node->kind,
                afferent: $afferent,
                efferent: $efferent,
                instability: $total === 0 ? 0.0 : round($efferent / $total, 2),
            );
        }

        return $metrics;
    }
}
