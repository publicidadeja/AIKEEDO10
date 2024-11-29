<?php

declare(strict_types=1);

namespace Billing\Infrastructure\Currency;

use Affiliate\Domain\ValueObjects\Amount;
use Billing\Domain\ValueObjects\Price;
use Shared\Domain\ValueObjects\CurrencyCode;
use Symfony\Component\Intl\Currencies;

class Exchange implements ExchangeInterface
{
    public function __construct(
        private RateProviderInterface $provider
    ) {}

    public function convert(
        Price|Amount $amount,
        CurrencyCode|string $from,
        CurrencyCode|string $to
    ): Price|Amount {
        if (is_string($from)) {
            $from = CurrencyCode::tryFrom($from) ?? CurrencyCode::USD;
        }

        if (is_string($to)) {
            $to = CurrencyCode::tryFrom($to) ?? CurrencyCode::USD;
        }

        if ($from->value === $to->value) {
            return $amount;
        }

        $rate = $this->provider->getRate($from, $to);

        $amount = $amount->value / (10 ** Currencies::getFractionDigits($from->value));
        $convertedAmount =  $rate * $amount * (10 ** Currencies::getFractionDigits($to->value));

        return new Price((int) round($convertedAmount));
    }
}
