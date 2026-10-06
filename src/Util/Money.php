<?php

namespace App\Util;

/**
 * Montants en centimes entiers : aucun calcul de prix ne passe par des flottants.
 */
final class Money
{
    private function __construct()
    {
    }

    /**
     * Convertit un montant décimal ("1199.99", "12,5", 12) en centimes.
     */
    public static function toCents(string|int|float|null $amount): int
    {
        if (null === $amount || '' === $amount) {
            return 0;
        }

        if (\is_int($amount)) {
            return $amount * 100;
        }

        if (\is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        $amount = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($amount));

        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $amount, $m)) {
            throw new \InvalidArgumentException(sprintf('Montant invalide : "%s".', $amount));
        }

        $decimals = $m[3] ?? '';
        $cents = (int) $m[2] * 100 + (int) str_pad(substr($decimals, 0, 2), 2, '0');

        // Arrondi au centime le plus proche si plus de deux décimales sont fournies.
        if (\strlen($decimals) > 2 && (int) $decimals[2] >= 5) {
            ++$cents;
        }

        return '-' === $m[1] ? -$cents : $cents;
    }

    /**
     * Centimes vers la forme décimale stockée en base ("1199.99").
     */
    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($cents, 100), $cents % 100);
    }

    /**
     * Affichage français : "1 199,99 €".
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '−' : '';
        $cents = abs($cents);
        $units = number_format(intdiv($cents, 100), 0, ',', "\u{202F}");

        return sprintf("%s%s,%02d\u{00A0}€", $sign, $units, $cents % 100);
    }
}
