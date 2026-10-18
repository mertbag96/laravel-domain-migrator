<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\Psr4Root;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * Laravel finds a model's factory, and a factory's model, from their class names.
 */
final readonly class FactoryDetector implements RiskDetector
{
    private const string HAS_FACTORY = 'Illuminate\\Database\\Eloquent\\Factories\\HasFactory';

    private const string USE_FACTORY = 'Illuminate\\Database\\Eloquent\\Attributes\\UseFactory';

    private const string USE_MODEL = 'Illuminate\\Database\\Eloquent\\Factories\\Attributes\\UseModel';

    private const string FACTORY_NAMESPACE = 'Database\\Factories\\';

    public function detect(PlanContext $context): array
    {
        $items = [];

        foreach ($context->moves as $move) {
            if ($move->kind !== ClassKind::Model || ! in_array(self::HAS_FACTORY, $context->project->traitsOf($move->class), true)) {
                continue;
            }

            $factory = $this->factoryName($move->class, $context->roots);
            $factoryFacts = $context->project->facts($factory);

            if ($factoryFacts === null) {
                continue;
            }

            if (! $this->linksItsFactory($context, $move->class)) {
                $items[] = new RiskItem($move->class, 'will look for its factory at '.$this->factoryName($move->target, $context->roots), $move->file);
            }

            if ($factoryFacts->modelProperty === null && ! in_array(self::USE_MODEL, $factoryFacts->attributes, true)) {
                $items[] = new RiskItem($factory, "guesses its model as {$move->class}", $factoryFacts->file, $factoryFacts->line);
            }
        }

        if ($items === []) {
            return [];
        }

        return [new MigrationRisk(
            RiskType::Factory,
            RiskLevel::Breaking,
            'Model factories',
            'Laravel finds a model\'s factory, and a factory\'s model, from their class names. After the move the names no longer match, so factory() and seeders fail.',
            'Add #[UseFactory(PostFactory::class)] or a newFactory() method to the model, and a $model property to the factory, before moving.',
            $items,
        )];
    }

    private function linksItsFactory(PlanContext $context, string $model): bool
    {
        foreach ($context->project->declarers($model) as $facts) {
            if ($facts->declares('newFactory')) {
                return true;
            }
        }

        foreach ($context->project->lineage($model) as $facts) {
            if (in_array(self::USE_FACTORY, $facts->attributes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The factory Factory::resolveFactoryName() guesses for a model.
     *
     * @param  list<Psr4Root>  $roots
     */
    private function factoryName(string $model, array $roots): string
    {
        $appNamespace = 'App\\';

        foreach ($roots as $root) {
            if ($root->contains($model)) {
                $appNamespace = $root->namespace;

                break;
            }
        }

        $relative = str_starts_with($model, $appNamespace.'Models\\')
            ? substr($model, strlen($appNamespace.'Models\\'))
            : (str_starts_with($model, $appNamespace) ? substr($model, strlen($appNamespace)) : $model);

        return self::FACTORY_NAMESPACE.$relative.'Factory';
    }
}
