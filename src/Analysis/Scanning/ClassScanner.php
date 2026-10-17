<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Analysis\Scanning;

use MertBag\DomainMigrator\Data\ScannedClass;
use MertBag\DomainMigrator\Data\SourceFile;
use MertBag\DomainMigrator\Exceptions\UnparsableFileException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final readonly class ClassScanner
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
    }

    /**
     * @return list<ScannedClass>
     *
     * @throws UnparsableFileException
     */
    public function scan(SourceFile $file): array
    {
        $code = @file_get_contents($file->absolutePath);

        if ($code === false) {
            throw UnparsableFileException::unreadable($file->relativePath);
        }

        try {
            $statements = $this->parser->parse($code) ?? [];
        } catch (Error $error) {
            throw UnparsableFileException::syntaxError($error);
        }

        $statements = (new NodeTraverser(new NameResolver))->traverse($statements);

        $visitor = new DependencyVisitor;
        (new NodeTraverser($visitor))->traverse($statements);

        return $visitor->result($file->relativePath);
    }
}
