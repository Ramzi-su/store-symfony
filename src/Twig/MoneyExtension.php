<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * {{ product.price|money }} => "19,99 $" (French typography: decimal comma, narrow
 * no-break space between thousands, no-break space before the currency sign).
 * Amounts are stored in cents and only converted to a decimal string for display.
 */
class MoneyExtension extends AbstractExtension
{
    private const THOUSANDS_SEPARATOR = "\u{202F}"; // narrow no-break space
    private const CURRENCY_SEPARATOR = "\u{00A0}";  // no-break space: "19,99" and "$" never split across lines

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

        $amount = number_format($cents / 100, 2, ',', self::THOUSANDS_SEPARATOR);

        return $amount . self::CURRENCY_SEPARATOR . '$';
    }
}
