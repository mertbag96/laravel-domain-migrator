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
    private const int CLASSES_PER_PATTERN = 200;

    /** @var list<string> */
    private array $patterns;

    /**
     * @param  list<string>  $classes
     */
    public function __construct(array $classes)
    {
        usort($classes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $this->patterns = array_map(
            fn (array $chunk): string => '/(?<![\w\\\\])\\\\{0,2}(?:'.implode('|', array_map($this->pattern(...), $chunk)).')(?![\w\\\\])/',
            array_chunk($classes, self::CLASSES_PER_PATTERN),
        );
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
        if ($this->patterns === [] || ! str_contains($text, '\\')) {
            return [];
        }

        $matches = [];

        foreach ($this->patterns as $pattern) {
            preg_match_all($pattern, $text, $found, PREG_OFFSET_CAPTURE);

            foreach ($found[0] as [$name, $offset]) {
                $matches[] = [(string) preg_replace('/\\\\+/', '\\', ltrim($name, '\\')), $offset];
            }
        }

        return $matches;
    }

    private function pattern(string $class): string
    {
        return implode('\\\\{1,2}', array_map(fn (string $segment): string => preg_quote($segment, '/'), explode('\\', $class)));
    }
}
