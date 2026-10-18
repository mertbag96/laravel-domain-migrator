<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Risks;

use MertBag\DomainMigrator\Data\ClassFacts;
use MertBag\DomainMigrator\Data\MigrationRisk;
use MertBag\DomainMigrator\Data\PlanContext;
use MertBag\DomainMigrator\Data\RiskItem;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\RiskLevel;
use MertBag\DomainMigrator\Enums\RiskType;

/**
 * Models whose class name ends up in a polymorphic *_type column.
 */
final readonly class MorphTypeDetector implements RiskDetector
{
    /** Relations that store the declaring model's own class name. */
    private const array OWN_TYPE_RELATIONS = ['morphMany', 'morphOne', 'morphToMany'];

    /** Package traits that add polymorphic relations, with the column they write. */
    private const array MORPH_TRAITS = [
        'Illuminate\\Notifications\\Notifiable' => 'notifications.notifiable_type',
        'Illuminate\\Notifications\\HasDatabaseNotifications' => 'notifications.notifiable_type',
        'Laravel\\Sanctum\\HasApiTokens' => 'personal_access_tokens.tokenable_type',
        'Spatie\\Permission\\Traits\\HasRoles' => 'model_has_roles.model_type',
        'Spatie\\Permission\\Traits\\HasPermissions' => 'model_has_permissions.model_type',
        'Spatie\\MediaLibrary\\InteractsWithMedia' => 'media.model_type',
        'Spatie\\Activitylog\\Traits\\LogsActivity' => 'activity_log.subject_type',
        'Spatie\\Activitylog\\Traits\\CausesActivity' => 'activity_log.causer_type',
        'Spatie\\Tags\\HasTags' => 'taggables.taggable_type',
        'OwenIt\\Auditing\\Auditable' => 'audits.auditable_type',
    ];

    public function detect(PlanContext $context): array
    {
        $morphMap = $context->project->morphMap ?? [];
        $morphedBy = $this->morphedByTargets($context);
        $items = [];

        foreach ($context->moves as $move) {
            if ($move->kind !== ClassKind::Model || in_array($move->class, $morphMap, true)) {
                continue;
            }

            foreach ($context->project->declarers($move->class) as $facts) {
                foreach (self::OWN_TYPE_RELATIONS as $relation) {
                    if (isset($facts->relationCalls[$relation])) {
                        $items[] = new RiskItem($move->class, "declares a {$relation}() relation", $facts->file, $facts->relationCalls[$relation]);
                    }
                }
            }

            foreach ($morphedBy[$move->class] ?? [] as $source) {
                $items[] = new RiskItem($move->class, 'is the related model of a morphedByMany() relation in '.class_basename($source->name), $source->file, $source->relationCalls['morphedByMany'] ?? null);
            }

            foreach ($context->project->traitsOf($move->class) as $trait) {
                if (isset(self::MORPH_TRAITS[$trait])) {
                    $items[] = new RiskItem($move->class, 'uses '.class_basename($trait).', which writes '.self::MORPH_TRAITS[$trait], $move->file);
                }
            }
        }

        if ($items === []) {
            return [];
        }

        return [new MigrationRisk(
            RiskType::MorphType,
            RiskLevel::Breaking,
            'Polymorphic types',
            sprintf(
                'These models are stored by class name in polymorphic *_type columns, and %s. Existing rows keep the old class name, so their relations stop resolving after the move.',
                $context->project->morphMap === null ? 'the app has no morph map' : 'they are missing from the morph map',
            ),
            "Before moving, give each model an alias with Relation::enforceMorphMap(['post' => Post::class]) and update the existing *_type values to the alias in a migration.",
            $items,
        )];
    }

    /**
     * @return array<string, list<ClassFacts>> Classes declaring morphedByMany(), keyed by the related class.
     */
    private function morphedByTargets(PlanContext $context): array
    {
        $targets = [];

        foreach ($context->project->facts as $facts) {
            foreach ($facts->morphedBy as $related) {
                $targets[$related][] = $facts;
            }
        }

        return $targets;
    }
}
