<?php

declare(strict_types=1);

namespace Ai\Domain\Speech;

use Billing\Domain\ValueObjects\Count;
use Psr\Http\Message\StreamInterface;

class GenerateSpeechResponse
{
    public function __construct(
        public readonly StreamInterface $audioContent,
        public readonly Count $cost
    ) {
    }
}