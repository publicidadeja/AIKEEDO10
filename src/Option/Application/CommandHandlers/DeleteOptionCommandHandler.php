<?php

declare(strict_types=1);

namespace Option\Application\CommandHandlers;

use Option\Application\Commands\DeleteOptionCommand;
use Option\Domain\Events\OptionDeletedEvent;
use Option\Domain\Exceptions\OptionNotFoundException;
use Option\Domain\Repositories\OptionRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class DeleteOptionCommandHandler
{
    public function __construct(
        private OptionRepositoryInterface $repo,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws OptionNotFoundException
     */
    public function handle(DeleteOptionCommand $cmd): void
    {
        // Find the option or throw an exception
        $option = $this->repo->ofId($cmd->id);

        // Delete the option from the repository
        $this->repo->remove($option);

        // Dispatch the option deleted event
        $event = new OptionDeletedEvent($option);
        $this->dispatcher->dispatch($event);
    }
}
