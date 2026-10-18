<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration;

use Illuminate\Support\Str;
use MertBag\DomainMigrator\Data\ClassMove;
use MertBag\DomainMigrator\Data\ClassNode;
use MertBag\DomainMigrator\Data\MigrationOptions;
use MertBag\DomainMigrator\Data\Psr4Root;

/**
 * Decides where a class of a domain moves to: App\Models\Order becomes App\Domain\Orders\Models\Order.
 *
 * The class keeps its path below its PSR-4 root, so controllers stay recognisable as controllers.
 * A namespace segment that repeats the domain name is dropped: App\Models\Labels\Label in the
 * Labels domain becomes App\Domain\Labels\Models\Label.
 */
final readonly class TargetResolver
{
    public function namespaceOf(string $domain, MigrationOptions $options): string
    {
        return $options->namespace.'\\'.Str::studly($domain);
    }

    public function isInPlace(string $class, string $domainNamespace): bool
    {
        return str_starts_with($class, $domainNamespace.'\\');
    }

    /**
     * @param  list<Psr4Root>  $roots  Longest namespace first.
     * @return ?ClassMove Null when the class or its target is outside every PSR-4 root.
     */
    public function resolve(ClassNode $node, string $domain, string $domainNamespace, array $roots): ?ClassMove
    {
        $root = $this->rootOf($node->name, $roots);

        if ($root === null) {
            return null;
        }

        $segments = explode('\\', substr($node->name, strlen($root->namespace)));
        $shortName = array_pop($segments);
        $domainKey = $this->key($domain);

        $segments = array_filter($segments, fn (string $segment): bool => $this->key($segment) !== $domainKey);
        $target = implode('\\', [$domainNamespace, ...$segments, $shortName]);
        $targetRoot = $this->rootOf($target, $roots);

        if ($targetRoot === null) {
            return null;
        }

        return new ClassMove($node->name, $target, $node->file, $targetRoot->pathOf($target), $node->kind);
    }

    /**
     * Where PSR-4 expects the class to be declared today.
     *
     * @param  list<Psr4Root>  $roots
     */
    public function expectedPath(string $class, array $roots): ?string
    {
        return $this->rootOf($class, $roots)?->pathOf($class);
    }

    /**
     * @param  list<Psr4Root>  $roots
     */
    private function rootOf(string $class, array $roots): ?Psr4Root
    {
        foreach ($roots as $root) {
            if ($root->contains($class)) {
                return $root;
            }
        }

        return null;
    }

    private function key(string $name): string
    {
        return Str::lower(Str::plural(Str::studly($name)));
    }
}
