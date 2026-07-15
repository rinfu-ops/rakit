<?php

namespace App\Domain\Catalog\ValueObjects;

use InvalidArgumentException;
use JsonException;
use stdClass;

final readonly class CatalogSpecifications
{
    /** @param array<mixed>|stdClass $value */
    private function __construct(public array|stdClass $value) {}

    public static function fromJson(?string $json): self
    {
        $json ??= '{}';

        try {
            $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Specifications must contain valid JSON.', previous: $exception);
        }

        if ($decoded instanceof stdClass) {
            return new self($decoded);
        }

        if (is_array($decoded) && $decoded === []) {
            return new self([]);
        }

        throw new InvalidArgumentException('Specifications must be a JSON object or an empty array.');
    }
}
