<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Migration\Scanning;

/**
 * Finds fully qualified class names in text, written with single or escaped double backslashes,
 * e.g. 'App\Models\Order' in a string or "App\\Models\\Order" in a JSON file.
 *
 * @internal
 */
final readonly class ClassNameMatcher
{
    /** Whole namespaced names, so App\Models\Post never matches inside App\Models\PostTag or MyApp\Models\Post. */
    private const string NAME = '/(?<![\w\\\\])\\\\{0,2}[A-Za-z_]\w*(?:\\\\{1,2}[A-Za-z_]\w*)+(?![\w\\\\])/';

    /** @var array<string, true> */
    private array $classes;

    /**
     * @param  list<string>  $classes
     */
    public function __construct(array $classes)
    {
        $this->classes = array_fill_keys($classes, true);
    }

    /**
     * @return list<string> The class names found, normalised to single backslashes.
     */
    public function match(string $text): array
    {
        return array_map(fn (array $match): string => $match[0], $this->matchWithOffsets($text));
    }

    /**
     * @return list<array{string, int}> Class name and byte offset of each match.
     */
    public function matchWithOffsets(string $text): array
    {
        if ($this->classes === [] || ! str_contains($text, '\\')) {
            return [];
        }

        preg_match_all(self::NAME, $text, $found, PREG_OFFSET_CAPTURE);
        $matches = [];

        foreach ($found[0] as [$name, $offset]) {
            $class = (string) preg_replace('/\\\\+/', '\\', ltrim($name, '\\'));

            if (isset($this->classes[$class])) {
                $matches[] = [$class, $offset];
            }
        }

        return $matches;
    }
}
