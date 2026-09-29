<?php

namespace App\Tests\Twig;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\TestCase;

class MoneyExtensionTest extends TestCase
{
    /**
     * @dataProvider amounts
     */
    public function testFormatsCentsAsDollars(?int $cents, string $expected): void
    {
        $this->assertSame($expected, (new MoneyExtension())->formatMoney($cents));
    }

    public static function amounts(): iterable
    {
        yield 'whole dollars' => [10000, '$100.00'];
        yield 'cents' => [1999, '$19.99'];
        yield 'less than a dollar' => [5, '$0.05'];
        yield 'thousands separator' => [123456789, '$1,234,567.89'];
        yield 'negative (refund)' => [-250, '-$2.50'];
        yield 'missing amount' => [null, ''];
    }
}
