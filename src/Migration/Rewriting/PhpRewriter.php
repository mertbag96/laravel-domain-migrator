<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Rewriting;

use MertBag\DomainMigrator\Data\RewrittenFile;
use MertBag\DomainMigrator\Migration\Scanning\ClassNameMatcher;
use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Rewrites one PHP file for a move: the namespace declaration, imports, fully qualified names and class
 * names in strings. Only the changed bytes are touched, so formatting and comments are kept.
 *
 * A name that used to resolve through the file's own namespace (no import) gets an import when the
 * class or the file moved away from it. Class names in comments and grouped imports are not rewritten
 * and are reported back to be updated by hand.
 */
final readonly class PhpRewriter
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    /**
     * @param  array<string, string>  $moves  Old class name => new class name, for every class that moves.
     * @param  array<string, true>  $known  Every class declared in the project, as keys.
     * @param  ?string  $newNamespace  The namespace the file moves to; null when the file stays where it is.
     *
     * @throws Error When the file cannot be parsed.
     */
    public function rewrite(string $code, string $file, array $moves, array $known, ?string $newNamespace = null): RewrittenFile
    {
        $statements = $this->parser->parse($code) ?? [];
        $collector = new RewriteCollector;
        (new NodeTraverser($collector))->traverse($statements);

        $namespace = $collector->namespace;
        $oldNamespace = $namespace?->name?->toString() ?? '';
        $newNamespace ??= $oldNamespace;

        $resolved = (new NodeTraverser(new NameResolver(null, ['preserveOriginalNames' => true, 'replaceNodes' => true])))->traverse($this->parser->parse($code) ?? []);
        $visitor = new RewriteVisitor($code, $moves, $known, $collector->imports, $oldNamespace, $newNamespace, $collector->declared);
        (new NodeTraverser($visitor))->traverse($resolved);

        $edits = $visitor->edits;
        $manual = $visitor->manual;

        if ($namespace?->name !== null && $newNamespace !== $oldNamespace) {
            $edits[] = [$namespace->name->getStartFilePos(), $namespace->name->getEndFilePos() + 1, $newNamespace];
        }

        foreach ($collector->useItems as [$name, $class, $grouped]) {
            if (! isset($moves[$class])) {
                continue;
            }

            if ($grouped) {
                $manual[] = sprintf('%s:%d grouped import of %s', $file, $name->getStartLine(), $class);

                continue;
            }

            $edits[] = [$name->getStartFilePos(), $name->getEndFilePos() + 1, $moves[$class]];
        }

        foreach ($collector->strings as $string) {
            $raw = substr($code, $string->getStartFilePos(), $string->getEndFilePos() - $string->getStartFilePos() + 1);
            $replaced = ClassNameMatcher::replace($raw, $moves);

            if ($replaced !== $raw) {
                $edits[] = [$string->getStartFilePos(), $string->getEndFilePos() + 1, $replaced];
            }
        }

        $eol = str_contains($code, "\r\n") ? "\r\n" : "\n";
        $imports = array_values(array_unique($visitor->imports));
        sort($imports);

        if ($imports !== []) {
            $edits[] = $this->importEdit($code, $collector, $imports, $eol, $file, $manual);
        }

        return new RewrittenFile($this->apply($code, array_filter($edits)), array_values(array_unique($manual)));
    }

    /**
     * @param  list<string>  $imports
     * @param  list<string>  $manual
     * @return array{int, int, string}|null
     */
    private function importEdit(string $code, RewriteCollector $collector, array $imports, string $eol, string $file, array &$manual): ?array
    {
        $lines = implode($eol, array_map(fn (string $class): string => "use {$class};", $imports));

        if ($collector->lastImport !== null) {
            return [$collector->lastImport->getEndFilePos() + 1, $collector->lastImport->getEndFilePos() + 1, $eol.$lines];
        }

        if ($collector->namespace !== null && $collector->namespace->getAttribute('kind') === Stmt\Namespace_::KIND_SEMICOLON) {
            $end = $collector->namespace->name !== null ? $collector->namespace->name->getEndFilePos() + 1 : null;
            $semicolon = $end === null ? false : strpos($code, ';', $end);

            if ($semicolon !== false) {
                return [$semicolon + 1, $semicolon + 1, $eol.$eol.$lines];
            }
        }

        $manual[] = sprintf('%s needs these imports: %s', $file, implode(', ', $imports));

        return null;
    }

    /**
     * @param  array<int, array{int, int, string}>  $edits  Start offset, end offset (exclusive) and replacement.
     */
    private function apply(string $code, array $edits): string
    {
        usort($edits, fn (array $a, array $b): int => [$b[0], $b[1]] <=> [$a[0], $a[1]]);

        foreach ($edits as [$start, $end, $replacement]) {
            $code = substr($code, 0, $start).$replacement.substr($code, $end);
        }

        return $code;
    }
}
