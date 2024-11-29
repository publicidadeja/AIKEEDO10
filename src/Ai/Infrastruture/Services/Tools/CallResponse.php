<?php

declare(strict_types=1);

namespace Ai\Infrastruture\Services\Tools;

use Ai\Domain\Entities\AbstractLibraryItemEntity;
use Billing\Domain\ValueObjects\Count;

class CallResponse
{
    public function __construct(
        public readonly string $content,
        public readonly Count $cost,
        public readonly ?AbstractLibraryItemEntity $item = null,
    ) {
    }
}
