<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use Closure;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * Gate guesses a model's policy from the model's namespace; a move can make the guess miss.
 */
final readonly class PolicyDetector implements RiskDetector
{
    private const string USE_POLICY = 'Illuminate\\Database\\Eloquent\\Attributes\\UsePolicy';

    /** Where policies are usually registered by hand with Gate::policy(). */
    private const array REGISTRATION_PATHS = ['app/Providers/', 'bootstrap/'];

    public function detect(PlanContext $context): array
    {
        $items = [];

        foreach ($context->graph->nodes as $model) {
            if ($model->kind !== ClassKind::Model) {
                continue;
            }

            $policy = $this->guess($model->name, $context->exists(...));

            if ($policy === null || ! ($context->isMoved($model->name) || $context->isMoved($policy)) || $this->isRegistered($context, $model->name, $policy)) {
                continue;
            }

            $guessAfterMove = $this->guess($context->renamed($model->name), $context->existsAfterMove(...));
            $policyAfterMove = $context->renamed($policy);

            if ($guessAfterMove !== $policyAfterMove) {
                $items[] = new RiskItem($model->name, sprintf(
                    'Gate will no longer find %s%s',
                    class_basename($policy),
                    $policyAfterMove === $policy ? '' : " at {$policyAfterMove}",
                ), $model->file);
            }
        }

        if ($items === []) {
            return [];
        }

        return [new MigrationRisk(
            RiskType::Policy,
            RiskLevel::Breaking,
            'Policy discovery',
            'Laravel guesses a model\'s policy from the model\'s namespace. After the move, the guess no longer finds these policies, so authorization checks deny or fail.',
            'Register the policies with Gate::policy() in a service provider, or add #[UsePolicy] to the models, before moving.',
            $items,
        )];
    }

    private function isRegistered(PlanContext $context, string $model, string $policy): bool
    {
        foreach ($context->project->lineage($model) as $facts) {
            if (in_array(self::USE_POLICY, $facts->attributes, true)) {
                return true;
            }
        }

        return $context->isReferencedFrom($model, self::REGISTRATION_PATHS) || $context->isReferencedFrom($policy, self::REGISTRATION_PATHS);
    }

    /**
     * The policy Gate::guessPolicyName() finds for a model, given which classes exist.
     *
     * @param  Closure(string): bool  $exists
     */
    private function guess(string $model, Closure $exists): ?string
    {
        $shortName = class_basename($model);
        $namespace = substr($model, 0, (int) strrpos($model, '\\'));
        $segments = explode('\\', $namespace);
        $candidates = [];

        for ($index = 1; $index <= count($segments); $index++) {
            $candidates[] = implode('\\', array_slice($segments, 0, $index)).'\\Policies\\'.$shortName.'Policy';
        }

        if (str_contains($namespace, '\\Models\\')) {
            $candidates[] = str_replace('\\Models\\', '\\Policies\\', $namespace).'\\'.$shortName.'Policy';
            $candidates[] = str_replace('\\Models\\', '\\Models\\Policies\\', $namespace).'\\'.$shortName.'Policy';
        }

        foreach (array_reverse($candidates) as $candidate) {
            if ($exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
