<?php

namespace App\Twig;

use App\Util\Money;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ShopExtension extends AbstractExtension
{
    /** @var array<string, string> */
    private array $icons = [];

    public function __construct(private readonly string $projectDir)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('eur', $this->eur(...)),
            new TwigFilter('cents', static fn (int $cents): string => Money::format($cents)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('icon', $this->icon(...)),
        ];
    }

    /**
     * Montant décimal tel que stocké en base ("1199.99") vers "1 199,99 €".
     */
    public function eur(string|int|float|null $amount): string
    {
        return Money::format(Money::toCents($amount));
    }

    /**
     * Icône SVG incluse dans la page (aucune police d'icônes, aucun appel externe).
     */
    public function icon(string $name, string $class = ''): Markup
    {
        if (!preg_match('/^[a-z0-9-]+$/', $name)) {
            throw new \InvalidArgumentException('Nom d\'icône invalide.');
        }

        if (!isset($this->icons[$name])) {
            $path = $this->projectDir . '/assets/icons/' . $name . '.svg';
            if (!is_file($path)) {
                throw new \InvalidArgumentException(sprintf('Icône inconnue : %s.', $name));
            }

            if (!preg_match('~<svg[^>]*>(.*)</svg>~s', (string) file_get_contents($path), $m)) {
                throw new \RuntimeException(sprintf('Icône illisible : %s.', $name));
            }

            $this->icons[$name] = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        $class = trim('icon ' . preg_replace('/[^A-Za-z0-9 _-]/', '', $class));

        return new Markup(sprintf(
            '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            $class,
            $this->icons[$name]
        ), 'UTF-8');
    }
}
