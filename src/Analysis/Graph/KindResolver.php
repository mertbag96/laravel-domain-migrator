<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Graph;

use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Enums\ClassKind;
use MertBag\DomainMigrator\Enums\DeclarationType;

/**
 * Determines the Laravel role of a class from, in order: its declaration type,
 * a known framework base class in its parent chain, a known framework
 * interface, its namespace and its name.
 */
final class KindResolver
{
    private const array BASE_CLASSES = [
        'Illuminate\\Database\\Eloquent\\Model' => ClassKind::Model,
        'Illuminate\\Database\\Eloquent\\Relations\\Pivot' => ClassKind::Model,
        'Illuminate\\Database\\Eloquent\\Relations\\MorphPivot' => ClassKind::Model,
        'Illuminate\\Foundation\\Auth\\User' => ClassKind::Model,
        'Illuminate\\Database\\Eloquent\\Builder' => ClassKind::Builder,
        'Illuminate\\Routing\\Controller' => ClassKind::Controller,
        'Illuminate\\Foundation\\Http\\FormRequest' => ClassKind::FormRequest,
        'Illuminate\\Http\\Resources\\Json\\JsonResource' => ClassKind::Resource,
        'Illuminate\\Http\\Resources\\Json\\ResourceCollection' => ClassKind::Resource,
        'Illuminate\\Notifications\\Notification' => ClassKind::Notification,
        'Illuminate\\Mail\\Mailable' => ClassKind::Mail,
        'Illuminate\\Console\\Command' => ClassKind::Command,
        'Illuminate\\Support\\ServiceProvider' => ClassKind::Provider,
        'Illuminate\\Foundation\\Support\\Providers\\AuthServiceProvider' => ClassKind::Provider,
        'Illuminate\\Foundation\\Support\\Providers\\EventServiceProvider' => ClassKind::Provider,
        'Illuminate\\Foundation\\Support\\Providers\\RouteServiceProvider' => ClassKind::Provider,
        'Exception' => ClassKind::Exception,
        'Error' => ClassKind::Exception,
        'RuntimeException' => ClassKind::Exception,
        'LogicException' => ClassKind::Exception,
        'DomainException' => ClassKind::Exception,
        'InvalidArgumentException' => ClassKind::Exception,
        'UnexpectedValueException' => ClassKind::Exception,
        'Symfony\\Component\\HttpKernel\\Exception\\HttpException' => ClassKind::Exception,
    ];

    private const array INTERFACES = [
        'Illuminate\\Contracts\\Validation\\ValidationRule' => ClassKind::Rule,
        'Illuminate\\Contracts\\Validation\\Rule' => ClassKind::Rule,
        'Illuminate\\Contracts\\Validation\\InvokableRule' => ClassKind::Rule,
        'Illuminate\\Contracts\\Database\\Eloquent\\CastsAttributes' => ClassKind::Cast,
        'Illuminate\\Contracts\\Database\\Eloquent\\CastsInboundAttributes' => ClassKind::Cast,
    ];

    private const array NAMESPACE_SEGMENTS = [
        'Http\\Controllers' => ClassKind::Controller,
        'Http\\Requests' => ClassKind::FormRequest,
        'Http\\Resources' => ClassKind::Resource,
        'Http\\Responses' => ClassKind::Response,
        'Http\\Middleware' => ClassKind::Middleware,
        'Console\\Commands' => ClassKind::Command,
        'Jobs' => ClassKind::Job,
        'Events' => ClassKind::Event,
        'Listeners' => ClassKind::Listener,
        'Observers' => ClassKind::Observer,
        'Policies' => ClassKind::Policy,
        'Notifications' => ClassKind::Notification,
        'Mail' => ClassKind::Mail,
        'Providers' => ClassKind::Provider,
        'Actions' => ClassKind::Action,
        'Services' => ClassKind::Service,
        'Repositories' => ClassKind::Repository,
        'Builders' => ClassKind::Builder,
        'Rules' => ClassKind::Rule,
        'Casts' => ClassKind::Cast,
        'Data' => ClassKind::Data,
        'DataTransferObjects' => ClassKind::Data,
        'Enums' => ClassKind::Enum,
        'Exceptions' => ClassKind::Exception,
    ];

    private const array SUFFIXES = [
        'ServiceProvider' => ClassKind::Provider,
        'Controller' => ClassKind::Controller,
        'Request' => ClassKind::FormRequest,
        'Resource' => ClassKind::Resource,
        'Response' => ClassKind::Response,
        'Middleware' => ClassKind::Middleware,
        'Job' => ClassKind::Job,
        'Event' => ClassKind::Event,
        'Listener' => ClassKind::Listener,
        'Observer' => ClassKind::Observer,
        'Policy' => ClassKind::Policy,
        'Notification' => ClassKind::Notification,
        'Command' => ClassKind::Command,
        'Action' => ClassKind::Action,
        'Service' => ClassKind::Service,
        'Repository' => ClassKind::Repository,
        'Builder' => ClassKind::Builder,
        'Cast' => ClassKind::Cast,
        'Data' => ClassKind::Data,
        'Dto' => ClassKind::Data,
        'Exception' => ClassKind::Exception,
    ];

    /** @var array<string, ScannedClass> */
    private array $classes = [];

    /** @var array<string, ClassKind> */
    private array $resolved = [];

    /**
     * @param  list<ScannedClass>  $classes
     */
    public function __construct(array $classes)
    {
        foreach ($classes as $class) {
            $this->classes[$class->name] ??= $class;
        }
    }

    public function resolve(ScannedClass $class): ClassKind
    {
        return $this->resolved[$class->name] ??= $this->determine($class, [$class->name => true]);
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function determine(ScannedClass $class, array $visited): ClassKind
    {
        return match ($class->declaration) {
            DeclarationType::Interface => ClassKind::Interface,
            DeclarationType::Trait => ClassKind::Trait,
            DeclarationType::Enum => ClassKind::Enum,
            DeclarationType::Class_ => $this->kindFromParent($class, $visited)
                ?? $this->kindFromInterfaces($class)
                ?? $this->kindFromModelsNamespace($class)
                ?? $this->kindFromNamespace($class->name)
                ?? $this->kindFromSuffix($class->name)
                ?? ClassKind::Other,
        };
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function kindFromParent(ScannedClass $class, array $visited): ?ClassKind
    {
        if ($class->parent === null) {
            return null;
        }

        if (isset(self::BASE_CLASSES[$class->parent])) {
            return self::BASE_CLASSES[$class->parent];
        }

        $parent = $this->classes[$class->parent] ?? null;

        if ($parent === null || isset($visited[$parent->name])) {
            return null;
        }

        $kind = $this->determine($parent, $visited + [$parent->name => true]);

        return $kind === ClassKind::Other ? null : $kind;
    }

    private function kindFromInterfaces(ScannedClass $class): ?ClassKind
    {
        foreach ($class->interfaces as $interface) {
            if (isset(self::INTERFACES[$interface])) {
                return self::INTERFACES[$interface];
            }
        }

        return null;
    }

    /**
     * Classes in a Models namespace count as models only when they extend a class
     * outside the application, such as a vendor model. Helpers that merely live in
     * that folder do not.
     */
    private function kindFromModelsNamespace(ScannedClass $class): ?ClassKind
    {
        if (! str_contains('\\'.$this->namespaceOf($class->name).'\\', '\\Models\\')) {
            return null;
        }

        $current = $class;
        $visited = [];

        while ($current->parent !== null && ! isset($visited[$current->name])) {
            $visited[$current->name] = true;
            $parent = $this->classes[$current->parent] ?? null;

            if ($parent === null) {
                return ClassKind::Model;
            }

            $current = $parent;
        }

        return null;
    }

    private function kindFromNamespace(string $class): ?ClassKind
    {
        $namespace = '\\'.$this->namespaceOf($class).'\\';

        foreach (self::NAMESPACE_SEGMENTS as $segment => $kind) {
            if (str_contains($namespace, '\\'.$segment.'\\')) {
                return $kind;
            }
        }

        return null;
    }

    private function kindFromSuffix(string $class): ?ClassKind
    {
        $shortName = substr($class, (int) strrpos($class, '\\') + 1);

        foreach (self::SUFFIXES as $suffix => $kind) {
            if ($shortName !== $suffix && str_ends_with($shortName, $suffix)) {
                return $kind;
            }
        }

        return null;
    }

    private function namespaceOf(string $class): string
    {
        return substr($class, 0, (int) strrpos($class, '\\'));
    }
}
