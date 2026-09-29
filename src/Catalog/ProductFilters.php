<?php

namespace App\Catalog;

use Symfony\Component\HttpFoundation\Request;

/**
 * Shop filters read from the query string, validated and normalised in one place.
 * Anything invalid falls back to "no filter" instead of reaching the database.
 */
final readonly class ProductFilters
{
    public const SORTS = [
        'recent' => 'Nouveautés',
        'prix-asc' => 'Prix croissant',
        'prix-desc' => 'Prix décroissant',
    ];
    public const DEFAULT_SORT = 'recent';

    private function __construct(
        public ?string $category,
        public string $query,
        public ?int $minPriceCents,
        public ?int $maxPriceCents,
        public bool $inStockOnly,
        public string $sort,
        public int $page,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $query = $request->query;

        $category = mb_substr(trim($query->getString('category')), 0, 50);
        $min = self::toCents($query->getString('min'));
        $max = self::toCents($query->getString('max'));
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $sort = $query->getString('tri', self::DEFAULT_SORT);

        return new self(
            category: $category !== '' ? $category : null,
            query: mb_substr(trim($query->getString('q')), 0, 100),
            minPriceCents: $min,
            maxPriceCents: $max,
            inStockOnly: $query->getString('stock') === '1',
            sort: \array_key_exists($sort, self::SORTS) ? $sort : self::DEFAULT_SORT,
            page: max(1, $query->getInt('page', 1)),
        );
    }

    /**
     * "12", "12.5" or "12,50" (dollars typed by the visitor) => cents; anything else => no filter.
     */
    private static function toCents(string $value): ?int
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || !is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (int) round((float) $value * 100);
    }

    public function isFiltered(): bool
    {
        return $this->category !== null || $this->query !== '' || $this->minPriceCents !== null
            || $this->maxPriceCents !== null || $this->inStockOnly;
    }
}
