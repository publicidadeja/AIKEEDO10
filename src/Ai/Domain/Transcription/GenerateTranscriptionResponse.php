<?php

declare(strict_types=1);

namespace Ai\Domain\Transcription;

use Ai\Domain\ValueObjects\Transcription;
use Billing\Domain\ValueObjects\Count;

class GenerateTranscriptionResponse
{
    public function __construct(
        public readonly Count $cost,
        public readonly Transcription $transcription,
    ) {
    }
}
