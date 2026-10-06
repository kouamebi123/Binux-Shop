<?php

namespace App\Tests\Unit;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Util\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * Régression : (int) ((float) "79.99" * 100) vaut 7998. Deux articles du catalogue (79,99 €)
     * partaient chez Stripe avec un centime de moins que le prix affiché.
     */
    public function testConversionEnCentimesExacte(): void
    {
        self::assertSame(7998, (int) ((float) '79.99' * 100), 'Le défaut d\'origine doit rester reproductible.');

        self::assertSame(7999, Money::toCents('79.99'));
        self::assertSame(1999, Money::toCents('19.99'));
        self::assertSame(895, Money::toCents('8.95'));
        self::assertSame(29, Money::toCents('0.29'));
        self::assertSame(119999, Money::toCents('1199.99'));
    }

    public function testAucunPrixNePerdDeCentime(): void
    {
        for ($cents = 1; $cents <= 250000; ++$cents) {
            $decimal = sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
            self::assertSame($cents, Money::toCents($decimal), $decimal);
            self::assertSame($decimal, Money::toDecimal($cents));
        }
    }

    public function testFormesAcceptees(): void
    {
        self::assertSame(0, Money::toCents(null));
        self::assertSame(0, Money::toCents(''));
        self::assertSame(1200, Money::toCents(12));
        self::assertSame(1250, Money::toCents('12,5'));
        self::assertSame(129990, Money::toCents("1\u{202F}299,90"));
        self::assertSame(7999, Money::toCents(79.99));
        self::assertSame(-346, Money::toCents('-3.456'));
    }

    public function testMontantInvalideRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toCents('12 euros');
    }

    public function testAffichageFrancais(): void
    {
        self::assertSame("1\u{202F}199,99\u{00A0}€", Money::format(119999));
        self::assertSame("0,07\u{00A0}€", Money::format(7));
        self::assertSame("2\u{202F}529,97\u{00A0}€", Money::format(252997));
    }

    public function testTotalDuPanierSansErreurDArrondi(): void
    {
        $cart = new Cart();
        foreach (['0.10' => 1, '0.20' => 1, '79.99' => 3] as $price => $quantity) {
            $item = (new CartItem())->setProduct((new Product())->setPrice($price))->setPrice((string) $price)->setQuantity($quantity);
            $cart->addItem($item);
        }

        // 0,10 + 0,20 + 3 × 79,99 = 240,27 exactement (en flottants : 240.26999999999998).
        self::assertSame(24027, $cart->getTotalCents());
        self::assertSame('240.27', $cart->getTotal());
    }

    public function testPourcentageDeReduction(): void
    {
        $product = (new Product())->setPrice('1199.99')->setOldPrice('1299.99');
        self::assertTrue($product->hasDiscount());
        self::assertSame(8, $product->getDiscountPercentage());

        // "99.99" > "1199.99" en comparaison de chaînes : la comparaison doit se faire en centimes.
        $product = (new Product())->setPrice('1199.99')->setOldPrice('99.99');
        self::assertFalse($product->hasDiscount());
        self::assertSame(0, $product->getDiscountPercentage());
    }
}
