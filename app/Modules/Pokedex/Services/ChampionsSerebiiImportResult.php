<?php

namespace App\Modules\Pokedex\Services;

final readonly class ChampionsSerebiiImportResult
{
    public function __construct(
        public bool $success,
        public ?string $failureReason = null,
        public ?string $serebiiUrl = null,
    ) {}

    public static function ok(): self
    {
        return new self(true);
    }

    public static function failed(string $reason, ?string $serebiiUrl = null): self
    {
        return new self(false, $reason, $serebiiUrl);
    }
}
