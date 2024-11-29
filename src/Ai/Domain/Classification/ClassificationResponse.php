<?php

declare(strict_types=1);

namespace Ai\Domain\Classification;

use Ai\Domain\ValueObjects\Classification;
use Billing\Domain\ValueObjects\Count;

class ClassificationResponse
{
    public function __construct(
        public readonly Count $cost,
        public readonly Classification $classification,
    ) {
    }
}
