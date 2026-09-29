<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * {{ product.price|money }} => "$19.99". Amounts are stored in cents and only
 * converted to a decimal string for display.
 */
class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'formatMoney']),
        ];
    }

    public function formatMoney(?int $cents): string
    {
        if ($cents === null) {
            return '';
        }

        $sign = $cents < 0 ? '-' : '';

        return $sign . '$' . number_format(abs($cents) / 100, 2, '.', ',');
    }
}
