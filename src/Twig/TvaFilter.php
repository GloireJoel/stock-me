<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TvaFilter extends AbstractExtension
{

    # French rate of value-added taxes
    private const TVA_RATE_NORMAL = 1.20;
    private const TVA_RATE_REDUCED = 1.05;

    public function getFunctions () : array
    {
        return [
            new TwigFunction('totalPrice', [$this, 'getTotalPrice']),
            new TwigFunction('operation', [$this, 'wichOperation'], ['is_safe' => ['html']])
        ];
    }

    public function getTotalPrice (float $price, float $quantity) : float
    {
        return $price * $quantity;
    }

    public function wichOperation(string $op): string
    {
        switch ($op) {
            case 'OUTPUT':
                return "<span class='text-success'>Entreé</span>";
            case 'INPUT':
                return "<span class='text-danger'>Sortie</span>";
            default:
                return $op;
        }
    }
}