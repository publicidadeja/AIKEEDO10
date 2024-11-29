<?php

declare(strict_types=1);

namespace User\Application\Commands;

use Shared\Infrastructure\CommandBus\Attributes\Handler;
use User\Application\CommandHandlers\CountUsersCommandHandler;
use User\Domain\ValueObjects\Role;
use User\Domain\ValueObjects\Status;

#[Handler(CountUsersCommandHandler::class)]
class CountUsersCommand
{
    public ?Status $status = null;
    public ?Role $role = null;

    /** Search terms/query */
    public ?string $query = null;

    public function setStatus(int $status): self
    {
        $this->status = Status::from($status);

        return $this;
    }

    public function setRole(int $role): self
    {
        $this->role = Role::from($role);

        return $this;
    }
}
