<?php

declare(strict_types=1);

namespace Ai\Application\CommandHandlers;

use Ai\Application\Commands\GenerateCodeDocumentCommand;
use Ai\Domain\Completion\CodeCompletionServiceInterface;
use Ai\Domain\Entities\DocumentEntity;
use Ai\Domain\Exceptions\InsufficientCreditsException;
use Ai\Domain\Repositories\LibraryItemRepositoryInterface;
use Ai\Domain\Services\AiServiceFactoryInterface;
use Ai\Domain\Entities\CodeDocumentEntity;
use Ai\Domain\Exceptions\ApiException;
use Ai\Domain\Exceptions\DomainException;
use Ai\Domain\Title\TitleServiceInterface;
use Ai\Domain\ValueObjects\Content;
use Ai\Domain\ValueObjects\Model;
use Ai\Domain\ValueObjects\RequestParams;
use Ai\Domain\ValueObjects\Token;
use Billing\Domain\Events\CreditUsageEvent;
use Billing\Domain\ValueObjects\Count;
use Generator;
use Preset\Domain\Exceptions\PresetNotFoundException;
use Preset\Domain\Placeholder\ParserService;
use Preset\Domain\Repositories\PresetRepositoryInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use User\Domain\Entities\UserEntity;
use User\Domain\Exceptions\UserNotFoundException;
use User\Domain\Repositories\UserRepositoryInterface;
use Workspace\Domain\Entities\WorkspaceEntity;
use Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Workspace\Domain\Repositories\WorkspaceRepositoryInterface;

class GenerateCodeDocumentCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private WorkspaceRepositoryInterface $wsRepo,
        private PresetRepositoryInterface $pRepo,
        private LibraryItemRepositoryInterface $repo,

        private ParserService $parser,
        private AiServiceFactoryInterface $factory,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @return Generator<int,Chunk,null,DocumentEntity>
     * @throws WorkspaceNotFoundException
     * @throws UserNotFoundException
     * @throws PresetNotFoundException
     * @throws InsufficientCreditsException
     * @throws ApiException
     * @throws DomainException
     */
    public function handle(GenerateCodeDocumentCommand $cmd): Generator
    {
        $ws = $cmd->workspace instanceof WorkspaceEntity
            ? $cmd->workspace
            : $this->wsRepo->ofId($cmd->workspace);

        $user = $cmd->user instanceof UserEntity
            ? $cmd->user
            : $this->userRepo->ofId($cmd->user);

        if (
            !is_null($ws->getTotalCreditCount()->value)
            && (float) $ws->getTotalCreditCount()->value <= 0
        ) {
            throw new InsufficientCreditsException();
        }

        $service = $this->factory->create(
            CodeCompletionServiceInterface::class,
            $cmd->model
        );

        $params = $cmd->params;
        $params['prompt'] = $cmd->prompt;
        $resp = $service->generateCodeCompletion(
            $cmd->model,
            $cmd->prompt,
            $cmd->language,
            $params
        );

        $content = '';
        foreach ($resp as $chunk) {
            if ($chunk->data instanceof Token) {
                $content .= (string) $chunk->data;
            }

            yield $chunk;
        }

        $service = $this->factory->create(
            TitleServiceInterface::class,
            $ws->getSubscription()
                ? $ws->getSubscription()->getPlan()->getConfig()->titler->model
                : new Model('gpt-3.5-turbo')
        );

        $content = new Content($content);
        $titleResp = $service->generateTitle(
            $content,
            $ws->getSubscription()
                ? $ws->getSubscription()->getPlan()->getConfig()->titler->model
                : new Model('gpt-3.5-turbo')
        );

        /** @var Count */
        $cost = $resp->getReturn();
        $cost = new Count($cost->value + $titleResp->cost->value);

        $entity = new CodeDocumentEntity(
            $ws,
            $user,
            $cmd->model,
            $titleResp->title,
            RequestParams::fromArray($cmd->params),
            $cost
        );
        $entity->setContent($content);
        $this->repo->add($entity);

        // Deduct credit from workspace
        $ws->deductCredit($cost);

        // Dispatch event
        $event = new CreditUsageEvent($ws, $cost);
        $this->dispatcher->dispatch($event);

        return $entity;
    }
}
