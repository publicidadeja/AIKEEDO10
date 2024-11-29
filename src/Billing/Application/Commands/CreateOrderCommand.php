<?php

declare(strict_types=1);

namespace Billing\Application\Commands;

use Billing\Application\CommandHandlers\CreateOrderCommandHandler;
use Billing\Domain\Entities\PlanEntity;
use Shared\Domain\ValueObjects\Id;
use Shared\Infrastructure\CommandBus\Attributes\Handler;
use Workspace\Domain\Entities\WorkspaceEntity;

#[Handler(CreateOrderCommandHandler::class)]
class CreateOrderCommand
{
    public WorkspaceEntity|Id $workspace;
    public PlanEntity|Id $plan;

    public function __construct(
        WorkspaceEntity|Id|string $workspace,
        PlanEntity|Id|string $plan
    ) {
        $this->workspace = is_string($workspace)
            ? new Id($workspace) : $workspace;

        $this->plan = is_string($plan)
            ? new Id($plan) : $plan;
    }
}
