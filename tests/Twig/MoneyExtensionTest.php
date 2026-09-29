<?php

namespace App\Tests\Twig;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyExtensionTest extends TestCase
{
    #[DataProvider('amounts')]
    public function testFormatsCentsAsDollars(?int $cents, string $expected): void
    {
        $this->assertSame($expected, (new MoneyExtension())->formatMoney($cents));
    }

    public static function amounts(): iterable
    {
        yield 'whole dollars' => [10000, "100,00\u{00A0}$"];
        yield 'cents' => [1999, "19,99\u{00A0}$"];
        yield 'less than a dollar' => [5, "0,05\u{00A0}$"];
        yield 'thousands separator' => [123456789, "1\u{202F}234\u{202F}567,89\u{00A0}$"];
        yield 'negative (refund)' => [-250, "-2,50\u{00A0}$"];
        yield 'missing amount' => [null, ''];
    }
}
