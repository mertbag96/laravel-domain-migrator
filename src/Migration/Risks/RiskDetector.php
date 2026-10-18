<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;

interface RiskDetector
{
    /**
     * @return list<MigrationRisk>
     */
    public function detect(PlanContext $context): array;
}
