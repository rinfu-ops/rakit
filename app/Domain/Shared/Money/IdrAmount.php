<?php

declare(strict_types=1);

namespace App\Domain\Shared\Money;

use InvalidArgumentException;

final readonly class IdrAmount
{
    private function __construct(public int $rupiah) {}

    public static function fromRupiah(mixed $rupiah): self
    {
        if (! is_int($rupiah)) {
            throw new InvalidArgumentException('IDR amount must be an integer number of rupiah.');
        }

        if ($rupiah < 0) {
            throw new InvalidArgumentException('IDR amount cannot be negative.');
        }

        return new self($rupiah);
    }
}
