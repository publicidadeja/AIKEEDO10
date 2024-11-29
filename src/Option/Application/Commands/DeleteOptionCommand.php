<?php

declare(strict_types=1);

namespace Option\Application\Commands;

use Option\Application\CommandHandlers\DeleteOptionCommandHandler;
use Shared\Domain\ValueObjects\Id;
use Shared\Infrastructure\CommandBus\Attributes\Handler;

#[Handler(DeleteOptionCommandHandler::class)]
class DeleteOptionCommand
{
    public Id $id;

    public function __construct(string $id)
    {
        $this->id = new Id($id);
    }
}
