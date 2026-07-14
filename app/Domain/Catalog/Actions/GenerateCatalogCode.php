<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\CatalogIdCounter;
use App\Domain\Catalog\ValueObjects\CatalogCodeAllocation;
use App\Domain\Catalog\ValueObjects\CatalogSequence;
use Illuminate\Support\Facades\DB;
use LogicException;

class GenerateCatalogCode
{
    public function handle(string $disciplineCode, string $itemTypeCode, string $groupCode): CatalogCodeAllocation
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Catalog code allocation requires an active transaction.');
        }

        CatalogIdCounter::query()->insertOrIgnore([
            'discipline_code' => $disciplineCode,
            'item_type_code' => $itemTypeCode,
            'group_code' => $groupCode,
            'last_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = CatalogIdCounter::query()
            ->where('discipline_code', $disciplineCode)
            ->where('item_type_code', $itemTypeCode)
            ->where('group_code', $groupCode)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence = CatalogSequence::nextAfter($counter->last_sequence)->value;
        $counter->forceFill(['last_sequence' => $sequence])->save();

        return new CatalogCodeAllocation(
            sprintf('%s-%s-%s-%04d', $disciplineCode, $itemTypeCode, $groupCode, $sequence),
            $sequence,
        );
    }

    public function reserveLocked(string $catalogCode, string $disciplineCode, string $itemTypeCode, string $groupCode): CatalogCodeAllocation
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Locked Catalog code reservation requires an active transaction.');
        }

        $pattern = sprintf(
            '/^%s-%s-%s-([0-9]{4,})$/',
            preg_quote($disciplineCode, '/'),
            preg_quote($itemTypeCode, '/'),
            preg_quote($groupCode, '/'),
        );
        if (! preg_match($pattern, $catalogCode, $parts)) {
            throw new LogicException('The locked Catalog code does not match its Catalog family.');
        }
        try {
            $sequence = CatalogSequence::fromLockedSuffix($parts[1])->value;
        } catch (\InvalidArgumentException $exception) {
            throw new LogicException('The locked Catalog code has an invalid sequence.', previous: $exception);
        }

        CatalogIdCounter::query()->insertOrIgnore([
            'discipline_code' => $disciplineCode,
            'item_type_code' => $itemTypeCode,
            'group_code' => $groupCode,
            'last_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = CatalogIdCounter::query()
            ->where('discipline_code', $disciplineCode)
            ->where('item_type_code', $itemTypeCode)
            ->where('group_code', $groupCode)
            ->lockForUpdate()
            ->firstOrFail();
        if ($counter->last_sequence < $sequence) {
            $counter->forceFill(['last_sequence' => $sequence])->save();
        }

        return new CatalogCodeAllocation($catalogCode, $sequence);
    }
}
