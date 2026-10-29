<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Rewriting;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * Turns the class names of a name-resolved tree into text edits for a move.
 *
 * @internal
 */
final class RewriteVisitor extends NodeVisitorAbstract
{
    /** @var list<array{int, int, string}> */
    public array $edits = [];

    /** @var list<string> Classes to import. */
    public array $imports = [];

    /** @var list<string> */
    public array $manual = [];

    /**
     * @param  array<string, string>  $moves
     * @param  array<string, true>  $known
     * @param  array<string, string>  $uses  Lower-case alias => imported class.
     * @param  array<string, true>  $declared  Lower-case short names declared in the file.
     */
    public function __construct(
        private readonly string $code,
        private readonly array $moves,
        private readonly array $known,
        private readonly array $uses,
        private readonly string $oldNamespace,
        private readonly string $newNamespace,
        private readonly array $declared,
    ) {}

    public function enterNode(Node $node): null
    {
        if (! $node instanceof Name\FullyQualified || $node->getStartFilePos() < 0) {
            return null;
        }

        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        $text = substr($this->code, $start, $end - $start + 1);
        $class = $node->toString();
        $new = $this->moves[$class] ?? $class;

        if (str_starts_with($text, '\\')) {
            if ($new !== $class) {
                $this->edits[] = [$start, $end + 1, '\\'.$new];
            }

            return null;
        }

        if (isset($this->uses[strtolower(explode('\\', $text)[0])])) {
            // A class import is rewritten with its use statement. A qualified name through an imported
            // namespace (use App\Http\Controllers\Restaurant; Restaurant\Kitchen::class) has no import of its own.
            if ($new !== $class && str_contains($text, '\\')) {
                $this->edits[] = [$start, $end + 1, '\\'.$new];
            }

            return null;
        }

        $this->relative($text, $class, $new, $start, $end);

        return null;
    }

    /**
     * A name that resolved through the namespace the file used to be in, without an import.
     */
    private function relative(string $text, string $class, string $new, int $start, int $end): void
    {
        $classMoved = $new !== $class;

        if (! $classMoved && ($this->newNamespace === $this->oldNamespace || (! isset($this->known[$class]) && $this->oldNamespace !== ''))) {
            return;
        }

        if (($this->newNamespace === '' ? $text : $this->newNamespace.'\\'.$text) === $new) {
            return;
        }

        $short = strtolower(basename(str_replace('\\', '/', $new)));

        if (str_contains($text, '\\') || $this->conflicts($short, $new)) {
            $this->edits[] = [$start, $end + 1, '\\'.$new];

            return;
        }

        if (($this->uses[$short] ?? null) !== $new) {
            $this->imports[] = $new;
        }
    }

    private function conflicts(string $short, string $new): bool
    {
        if (isset($this->uses[$short]) && $this->uses[$short] !== $new) {
            return true;
        }

        return isset($this->declared[$short]) && ($this->newNamespace === '' ? '' : $this->newNamespace.'\\').$short !== strtolower($new);
    }
}
