<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * {{ product.category|category_label }} => "Téléphones".
 * Categories are stored as English identifiers; this is the single place that names them for visitors.
 */
class CatalogExtension extends AbstractExtension
{
    private const LABELS = [
        'phones' => 'Téléphones',
        'watches' => 'Montres',
        'headphones' => 'Audio',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('category_label', [$this, 'categoryLabel']),
        ];
    }

    public function categoryLabel(?string $category): string
    {
        if ($category === null || $category === '') {
            return '';
        }

        // Unknown (new) categories stay readable: "smart-home" => "Smart home".
        return self::LABELS[$category] ?? ucfirst(str_replace(['-', '_'], ' ', $category));
    }
}
