<?php

declare(strict_types=1);

namespace Ai\Domain\Embedding;

use Ai\Domain\ValueObjects\Embedding;
use Billing\Domain\ValueObjects\Count;

class EmbeddingResponse
{
    public function __construct(
        public readonly Embedding $embedding,
        public readonly Count $cost,
    ) {
    }
}
