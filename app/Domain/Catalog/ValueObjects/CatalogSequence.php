<?php

namespace App\Domain\Catalog\ValueObjects;

use Brick\Math\BigInteger;
use DomainException;
use InvalidArgumentException;

final readonly class CatalogSequence
{
    private const POSTGRES_BIGINT_MAX = '9223372036854775807';

    private function __construct(public int $value) {}

    public static function fromLockedSuffix(string $suffix): self
    {
        if (! preg_match('/^[0-9]+$/D', $suffix)) {
            throw new InvalidArgumentException('The locked Catalog sequence is malformed.');
        }

        $sequence = BigInteger::of($suffix);
        if ($sequence->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('The locked Catalog sequence must be positive.');
        }
        if ($sequence->isGreaterThan(BigInteger::of(self::POSTGRES_BIGINT_MAX))) {
            throw new InvalidArgumentException('The locked Catalog sequence exceeds the PostgreSQL bigint limit.');
        }

        return new self($sequence->toInt());
    }

    public static function nextAfter(int|string $current): self
    {
        $sequence = BigInteger::of($current);
        if ($sequence->isNegative()) {
            throw new DomainException('The Catalog sequence counter is invalid.');
        }
        if ($sequence->isGreaterThanOrEqualTo(BigInteger::of(self::POSTGRES_BIGINT_MAX))) {
            throw new DomainException('The Catalog sequence counter is exhausted.');
        }

        return new self($sequence->plus(1)->toInt());
    }
}
