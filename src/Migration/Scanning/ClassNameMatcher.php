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

    /**
     * Rewrites the matched class names that are keys of the map, keeping the writing style of each one
     * (a leading backslash, single or escaped double backslashes).
     *
     * @param  array<string, string>  $map  Old class name => new class name.
     */
    public static function replace(string $text, array $map): string
    {
        if ($map === [] || ! str_contains($text, '\\')) {
            return $text;
        }

        return (string) preg_replace_callback(self::NAME, static function (array $match) use ($map): string {
            $class = (string) preg_replace('/\\\\+/', '\\', ltrim($match[0], '\\'));

            if (! isset($map[$class])) {
                return $match[0];
            }

            $new = $map[$class];

            if (str_contains($match[0], '\\\\')) {
                $new = str_replace('\\', '\\\\', $new);
            }

            return substr($match[0], 0, strspn($match[0], '\\')).$new;
        }, $text);
    }
}
