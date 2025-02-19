<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Api\Presets;

use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use Presentation\Resources\CountResource;
use Presentation\Response\JsonResponse;
use Presentation\Validation\ValidationException;
use Preset\Application\Commands\CountPresetsCommand;
use Preset\Domain\ValueObjects\Status;
use Preset\Domain\ValueObjects\Type;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Domain\ValueObjects\Id;
use Shared\Infrastructure\CommandBus\Dispatcher;
use Shared\Infrastructure\CommandBus\Exception\NoHandlerFoundException;
use Workspace\Domain\Entities\WorkspaceEntity;

#[Route(path: '/count', method: RequestMethod::GET)]
class CountPresetsRequestHandler extends PresetApi implements
    RequestHandlerInterface
{
    /**
     * @param Dispatcher $dispatcher 
     * @return void 
     */
    public function __construct(
        private Dispatcher $dispatcher
    ) {}

    /**
     * @param ServerRequestInterface $request 
     * @return ResponseInterface 
     * @throws ValidationException 
     * @throws NoHandlerFoundException 
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var WorkspaceEntity */
        $ws = $request->getAttribute(WorkspaceEntity::class);

        $params = (object) $request->getQueryParams();

        $cmd = new CountPresetsCommand();
        $cmd->status = Status::from(1);

        $config = $ws->getSubscription()?->getPlan()->getConfig();
        if ($config && $config->presets !== null && !isset($params->all)) {
            $cmd->setIds(...$config->presets);
        }

        if (property_exists($params, 'type')) {
            $cmd->type = Type::from($params->type);
        }

        if (property_exists($params, 'category')) {
            $cmd->category = new Id($params->category);
        }

        if (property_exists($params, 'query') && $params->query) {
            $cmd->query = $params->query;
        }

        /** @var int */
        $count = $this->dispatcher->dispatch($cmd);
        return new JsonResponse(new CountResource($count));
    }
}
