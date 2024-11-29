<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Api\Voices;

use Easy\Container\Attributes\Inject;
use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use Presentation\Resources\CountResource;
use Presentation\Response\JsonResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Infrastructure\CommandBus\Dispatcher;
use Voice\Application\Commands\CountVoicesCommand;
use Voice\Domain\ValueObjects\Status;
use Workspace\Domain\Entities\WorkspaceEntity;

#[Route(path: '/count', method: RequestMethod::GET)]
class CountVoicesRequestHandler extends VoiceApi implements
    RequestHandlerInterface
{
    public function __construct(
        private Dispatcher $dispatcher,

        #[Inject('option.features.voiceover.models')]
        private ?array $models = null
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var WorkspaceEntity */
        $ws = $request->getAttribute(WorkspaceEntity::class);

        $params = (object) $request->getQueryParams();

        $cmd = new CountVoicesCommand();
        $cmd->status = Status::from(1);

        if ($this->models) {
            $models = $this->models;

            $config = $ws->getSubscription()?->getPlan()->getConfig();
            if ($config && !isset($params->all)) {
                $models = array_filter(
                    $models,
                    fn($model) => isset($config->models[$model]) && $config->models[$model]
                );
            }

            $cmd->setModels(...$models);
        }

        if (property_exists($params, 'provider')) {
            $cmd->setProvider($params->provider);
        }

        if (property_exists($params, 'tone')) {
            $cmd->setTone($params->tone);
        }

        if (property_exists($params, 'use_case')) {
            $cmd->setUseCase($params->use_case);
        }

        if (property_exists($params, 'gender')) {
            $cmd->setGender($params->gender);
        }

        if (property_exists($params, 'accent')) {
            $cmd->setAccent($params->accent);
        }

        if (property_exists($params, 'language')) {
            $cmd->setLanguageCode($params->language);
        }

        if (property_exists($params, 'query') && $params->query) {
            $cmd->query = $params->query;
        }

        /** @var int */
        $count = $this->dispatcher->dispatch($cmd);
        return new JsonResponse(new CountResource($count));
    }
}
