<?php

declare(strict_types=1);

namespace MertBag\DomainMigrator\Data;

/**
 * One class affected by a risk, with the evidence that was found.
 */
final readonly class RiskItem
{
    public function __construct(
        public string $class,
        public string $detail,
        public ?string $file = null,
        public ?int $line = null,
    ) {}

    public function location(): ?string
    {
        if ($this->file === null) {
            return null;
        }

        return $this->line === null ? $this->file : "{$this->file}:{$this->line}";
    }

    /**
     * @return array{class: string, detail: string, file: ?string, line: ?int}
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'detail' => $this->detail,
            'file' => $this->file,
            'line' => $this->line,
        ];
    }
}
