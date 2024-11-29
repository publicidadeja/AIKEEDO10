<?php

declare(strict_types=1);

namespace Ai\Infrastruture\Services;

use Ai\Domain\ValueObjects\Model;
use Billing\Domain\ValueObjects\Count;
use Easy\Container\Attributes\Inject;

class CostCalculator
{
    public const INPUT = 1;
    public const OUTPUT = 2;
    public const SIZE_256x256 = 4;
    public const SIZE_512x512 = 8;
    public const SIZE_1024x1024 = 16;
    public const SIZE_1024x1792 = 32;
    public const SIZE_1792x1024 = 64;
    public const QUALITY_SD = 128;
    public const QUALITY_HD = 256;

    private int $bitmask = 0;

    public function __construct(
        #[Inject('option.credit_rate')]
        private array $rates = []
    ) {
        $this->calculateBitMask();
    }

    /**
     * Example usage: 
     * $cost = $costCalculator->calculate(
     *     1, 
     *     new Model('gpt-3.5-turbo-instruct'),
     *     CostCalculator::QUALITY_HD | CostCalculator::SIZE_1024x1024
     *  );
     */
    public function calculate(float|int $amount, Model $model, ?int $opt = null): Count
    {
        if (isset($this->rates[$model->value])) {
            return new Count($amount * (float) $this->rates[$model->value]);
        }

        if (!is_null($opt) && !($this->bitmask & $opt)) {
            return new Count(0);
        }

        if (($opt & self::INPUT) && isset($this->rates[$model->value . "-input"])) {
            return new Count($amount * (float) $this->rates[$model->value . "-input"]);
        }

        if (isset($this->rates[$model->value . "-output"])) {
            return new Count($amount * (float) $this->rates[$model->value . "-output"]);
        }

        if (in_array(
            $model->value,
            [
                'eleven_multilingual_v2',
                'eleven_turbo_v2_5',
                'eleven_multilingual_v1',
                'eleven_monolingual_v1'
            ]
        )) {
            return new Count($amount * (float)($this->rates['elevenlabs'] ?? 0));
        }

        if ($model->value === 'dall-e-3') {
            $quality  = $opt & self::QUALITY_SD ? 'standard' : 'hd';
            $size = $opt & self::SIZE_1024x1024 ? '1024' : '1792';

            return new Count($amount * (float)($this->rates['dall-e-3-' . $quality . '-' . $size] ?? 0));
        }

        if ($model->value === 'dall-e-2') {
            $size = '1024';

            if ($opt & self::SIZE_512x512) {
                $size = '512';
            } elseif ($opt & self::SIZE_256x256) {
                $size = '256';
            }

            return new Count($amount * (float)($this->rates['dall-e-2-' . $size] ?? 0));
        }

        // Add a return statement here to ensure a Count object is returned in all cases
        return new Count(0);
    }

    private function calculateBitMask(): void
    {
        $this->bitmask =
            self::INPUT
            | self::OUTPUT
            | self::SIZE_256x256
            | self::SIZE_512x512
            | self::SIZE_1024x1024
            | self::SIZE_1024x1792
            | self::SIZE_1792x1024
            | self::QUALITY_SD
            | self::QUALITY_SD
            | self::QUALITY_HD;
    }
}
