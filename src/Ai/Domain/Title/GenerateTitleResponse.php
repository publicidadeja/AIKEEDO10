<?php

namespace Ai\Domain\Title;

use Ai\Domain\ValueObjects\Title;
use Billing\Domain\ValueObjects\Count;

class GenerateTitleResponse
{
    public function __construct(
        public readonly Title $title,
        public readonly Count $cost
    ) {
    }
}
