<?php

namespace App\Domain\Catalog\Imports;

use App\Domain\Catalog\ValueObjects\CatalogSequence;
use App\Domain\Pricing\Enums\PriceBasis;
use App\Domain\Pricing\Enums\TaxContext;
use App\Domain\Shared\Normalization\NormalizesText;
use App\Domain\Shared\Normalization\NormalizesUnit;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReadBaselineCatalogWorkbook
{
    private const MAX_EXACT_FLOAT_INTEGER = '9007199254740991';

    private const POSTGRES_BIGINT_MAX = '9223372036854775807';

    public const HEADERS = [
        'source_item_id', 'source_line_id', 'discipline_code', 'item_type_code',
        'category_code', 'category_name', 'group_code', 'group_name',
        'standard_name', 'standard_description', 'unit', 'alias',
        'unit_price_rupiah', 'quantity', 'observed_at', 'price_basis', 'tax_context',
    ];

    public function __construct(
        private readonly NormalizesText $textNormalizer,
        private readonly NormalizesUnit $unitNormalizer,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function handle(string $privatePath): array
    {
        if (Str::contains($privatePath, ['..', '\\']) || Str::startsWith($privatePath, '/')) {
            throw new InvalidArgumentException('The baseline path must remain inside private local storage.');
        }

        if (! Str::endsWith(Str::lower($privatePath), '.xlsx') || ! Storage::disk('local')->exists($privatePath)) {
            throw new InvalidArgumentException('The baseline source must be an existing private .xlsx file.');
        }

        if (Storage::disk('local')->size($privatePath) > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('The baseline workbook exceeds 25 MB.');
        }

        $reader = new Xlsx;
        $reader->setReadDataOnly(false);
        $absolutePath = Storage::disk('local')->path($privatePath);
        $worksheetNames = $reader->listWorksheetNames($absolutePath);
        $worksheetInfo = $reader->listWorksheetInfo($absolutePath);

        if ($worksheetNames !== ['Catalog']) {
            throw new InvalidArgumentException('The workbook must contain exactly one worksheet named Catalog.');
        }

        if ($worksheetInfo[0]['totalRows'] > 10001 || $worksheetInfo[0]['totalColumns'] > count(self::HEADERS)) {
            throw new InvalidArgumentException('The baseline workbook exceeds its row or column limit.');
        }

        $reader->setLoadSheetsOnly(['Catalog']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $worksheetName === 'Catalog' && $row <= 10001 && ord($columnAddress[0]) <= ord('Q');
            }
        });
        $spreadsheet = $reader->load($absolutePath);

        $sheet = $spreadsheet->getSheet(0);
        $this->assertHeaders($sheet);

        $rows = [];
        $lineIds = [];

        for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $values = [];
            foreach (self::HEADERS as $offset => $header) {
                $cell = $sheet->getCell([$offset + 1, $rowNumber]);
                if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                    throw new InvalidArgumentException("Formula cells are not allowed at row {$rowNumber}.");
                }
                $values[$header] = $cell->getValue();
            }

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $row = $this->validateRow($values, $rowNumber);
            if (isset($lineIds[$row['source_line_id']])) {
                throw new InvalidArgumentException("Duplicate source_line_id at row {$rowNumber}.");
            }
            $lineIds[$row['source_line_id']] = true;
            $rows[] = $row;
        }

        if ($rows === []) {
            throw new InvalidArgumentException('The baseline workbook contains no data rows.');
        }

        return $rows;
    }

    private function assertHeaders(Worksheet $sheet): void
    {
        $actual = [];
        foreach (self::HEADERS as $offset => $header) {
            $actual[] = $sheet->getCell([$offset + 1, 1])->getValue();
        }
        if ($actual !== self::HEADERS || $sheet->getHighestDataColumn() !== 'Q') {
            throw new InvalidArgumentException('The Catalog worksheet headers do not match the Phase 3 baseline contract.');
        }
    }

    /** @param array<string, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        return collect($values)->every(fn (mixed $value): bool => $value === null || trim((string) $value) === '');
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function validateRow(array $values, int $rowNumber): array
    {
        foreach (['source_item_id', 'source_line_id', 'discipline_code', 'item_type_code', 'category_code', 'category_name', 'group_code', 'group_name', 'standard_name', 'unit'] as $field) {
            if (! is_string($values[$field]) || trim($values[$field]) === '') {
                throw new InvalidArgumentException("{$field} is required as text at row {$rowNumber}.");
            }
        }

        foreach ($values as $field => $value) {
            if (is_string($value) && Str::length($value) > 5000) {
                throw new InvalidArgumentException("{$field} exceeds 5,000 characters at row {$rowNumber}.");
            }
        }

        if (Str::length($values['source_item_id']) > 100 || Str::length($values['source_line_id']) > 100) {
            throw new InvalidArgumentException("Source identity is too long at row {$rowNumber}.");
        }

        $codes = [];
        foreach (['discipline_code', 'item_type_code', 'category_code', 'group_code'] as $field) {
            $codes[$field] = Str::upper(trim($values[$field]));
            if (! preg_match('/^[A-Z0-9]{2,20}$/', $codes[$field])) {
                throw new InvalidArgumentException("{$field} is invalid at row {$rowNumber}.");
            }
        }

        $catalogCodePattern = sprintf(
            '/^%s-%s-%s-([0-9]{4,})$/',
            preg_quote($codes['discipline_code'], '/'),
            preg_quote($codes['item_type_code'], '/'),
            preg_quote($codes['group_code'], '/'),
        );
        if (! preg_match($catalogCodePattern, Str::upper(trim($values['source_item_id'])), $catalogCodeParts)) {
            throw new InvalidArgumentException("source_item_id must be the approved locked Catalog ID at row {$rowNumber}.");
        }
        try {
            CatalogSequence::fromLockedSuffix($catalogCodeParts[1]);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException("source_item_id must be the approved locked Catalog ID at row {$rowNumber}.");
        }

        $priceFields = ['unit_price_rupiah', 'observed_at', 'price_basis', 'tax_context'];
        $hasPrice = collect($priceFields)->contains(fn (string $field): bool => $values[$field] !== null && trim((string) $values[$field]) !== '');
        if ($hasPrice && collect($priceFields)->contains(fn (string $field): bool => $values[$field] === null || trim((string) $values[$field]) === '')) {
            throw new InvalidArgumentException("Historical price fields are incomplete at row {$rowNumber}.");
        }

        $price = null;
        $quantity = null;
        $observedAt = null;
        $priceBasis = null;
        $taxContext = null;
        if ($hasPrice) {
            $price = $this->nonNegativeInteger($values['unit_price_rupiah'], 'unit_price_rupiah', $rowNumber);
            $quantity = $values['quantity'] === null || trim((string) $values['quantity']) === ''
                ? null
                : $this->canonicalQuantity($values['quantity'], $rowNumber);
            $observedAt = CarbonImmutable::createFromFormat('!Y-m-d', (string) $values['observed_at']);
            if ($observedAt === false || $observedAt->format('Y-m-d') !== $values['observed_at']) {
                throw new InvalidArgumentException("observed_at must be YYYY-MM-DD at row {$rowNumber}.");
            }
            $priceBasis = PriceBasis::tryFrom((string) $values['price_basis'])
                ?? throw new InvalidArgumentException("price_basis is invalid at row {$rowNumber}.");
            $taxContext = TaxContext::tryFrom((string) $values['tax_context'])
                ?? throw new InvalidArgumentException("tax_context is invalid at row {$rowNumber}.");
        }

        $standardName = Str::squish($values['standard_name']);
        $standardDescription = $values['standard_description'] === null || trim((string) $values['standard_description']) === '' ? null : Str::squish((string) $values['standard_description']);
        $normalizedUnit = $this->unitNormalizer->normalize($values['unit']);
        $itemIdentity = [
            'discipline_code' => $codes['discipline_code'], 'item_type_code' => $codes['item_type_code'],
            'category_code' => $codes['category_code'], 'category_name' => Str::squish($values['category_name']),
            'group_code' => $codes['group_code'], 'group_name' => Str::squish($values['group_name']),
            'standard_name' => $standardName, 'standard_description' => $standardDescription,
            'normalized_unit' => $normalizedUnit,
        ];

        return [...$itemIdentity,
            'source_item_id' => Str::upper(trim($values['source_item_id'])),
            'source_line_id' => Str::squish($values['source_line_id']),
            'normalized_name' => $this->textNormalizer->normalize($standardName),
            'alias' => $values['alias'] === null || trim((string) $values['alias']) === '' ? null : Str::squish((string) $values['alias']),
            'unit_price_rupiah' => $price, 'quantity' => $quantity, 'observed_at' => $observedAt,
            'price_basis' => $priceBasis, 'tax_context' => $taxContext,
            'content_hash' => hash('sha256', json_encode($itemIdentity, JSON_THROW_ON_ERROR)),
        ];
    }

    private function nonNegativeInteger(mixed $value, string $field, int $row): int
    {
        try {
            if (is_int($value)) {
                $integer = BigInteger::of($value);
            } elseif (is_string($value)) {
                if (! preg_match('/^[0-9]+$/D', $value)) {
                    throw new InvalidArgumentException("{$field} must be a non-negative integer at row {$row}.");
                }
                $integer = BigInteger::of($value);
            } elseif (is_float($value)) {
                $integer = BigDecimal::fromFloatShortest($value)->toBigInteger();
                if ($integer->isGreaterThan(BigInteger::of(self::MAX_EXACT_FLOAT_INTEGER))) {
                    throw new InvalidArgumentException("{$field} is not safely representable as an exact integer at row {$row}; store it as integer text.");
                }
            } else {
                throw new InvalidArgumentException("{$field} must be a non-negative integer at row {$row}.");
            }
        } catch (MathException) {
            throw new InvalidArgumentException("{$field} must be a non-negative integer at row {$row}.");
        }

        if ($integer->isNegative()) {
            throw new InvalidArgumentException("{$field} must be a non-negative integer at row {$row}.");
        }
        if ($integer->isGreaterThan(BigInteger::of(self::POSTGRES_BIGINT_MAX))) {
            throw new InvalidArgumentException("{$field} exceeds the PostgreSQL bigint limit at row {$row}.");
        }

        return $integer->toInt();
    }

    private function canonicalQuantity(mixed $value, int $row): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new InvalidArgumentException("quantity must be a non-negative decimal with at most four decimal places at row {$row}.");
        }

        try {
            if (is_float($value)) {
                $quantity = BigDecimal::fromFloatShortest($value);
            } else {
                $raw = trim((string) $value);
                if (! preg_match('/^\+?[0-9]+(?:\.[0-9]+)?$/D', $raw)) {
                    throw new InvalidArgumentException("quantity must be a non-negative decimal with at most four decimal places at row {$row}.");
                }
                $quantity = BigDecimal::of($raw);
            }

            if ($quantity->isNegative()) {
                throw new InvalidArgumentException("quantity must be a non-negative decimal with at most four decimal places at row {$row}.");
            }

            $quantity = $quantity->toScale(4, RoundingMode::Unnecessary);
        } catch (MathException) {
            throw new InvalidArgumentException("quantity must be a non-negative decimal with at most four decimal places at row {$row}.");
        }

        $integerDigits = ltrim($quantity->getIntegralPart()->toString(), '0');
        if (strlen($integerDigits) > 16) {
            throw new InvalidArgumentException("quantity exceeds the decimal(20,4) precision limit at row {$row}.");
        }

        return (string) $quantity;
    }
}
