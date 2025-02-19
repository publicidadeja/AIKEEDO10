<?php


declare(strict_types=1);

namespace Ai\Infrastruture\Services\Tools;

use Ai\Domain\Embedding\EmbeddingServiceInterface;
use Ai\Domain\Services\AiServiceFactoryInterface;
use Ai\Domain\ValueObjects\Model;
use Easy\Container\Attributes\Inject;
use Override;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

class EmbeddingSearch implements ToolInterface
{
    public const LOOKUP_KEY = 'embedding_search';

    public function __construct(
        private AiServiceFactoryInterface $factory,

        #[Inject('option.features.tools.embedding_search.is_enabled')]
        private ?bool $isEnabled = null,
    ) {}


    #[Override]
    public function isEnabled(): bool
    {
        return (bool) $this->isEnabled;
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Retrieves the information for the search query based on the uploaded files. Returns the most relevant results in JSON-encoded format. Use only when uploaded files are available.';
    }

    #[Override]
    public function getDefinitions(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "query" => [
                    "type" => "string",
                    "description" => "Search query"
                ],
            ],
            "required" => ["query"]
        ];
    }

    #[Override]
    public function call(
        UserEntity $user,
        WorkspaceEntity $workspace,
        array $params = [],
        array $files = [],
        array $knowledgeBase = [],
    ): CallResponse {
        $query = $params['query'];
        $results = [];

        $model = new Model('text-embedding-3-small'); // Default model
        $sub = $workspace->getSubscription();
        if ($sub) {
            $model = $sub->getPlan()->getConfig()->embeddingModel;
        }

        $service = $this->factory->create(
            EmbeddingServiceInterface::class,
            $model
        );

        $resp = $service->generateEmbedding($model, $query);

        foreach ($files as $file) {
            $embeddings = $file->getEmbedding()->value;

            if (!$embeddings) {
                continue;
            }

            foreach ($embeddings as $em) {
                $similarity = $this->cosineSimilarity(
                    $em['embedding'],
                    $resp->embedding->value[0]['embedding']
                );

                $results[] = [
                    'content' => $em['content'],
                    'similarity' => $similarity
                ];
            }
        }

        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        $results = array_slice($results, 0, 5);
        $texts = array_map(function ($r) {
            return $r['content'];
        }, $results);

        return new CallResponse(
            json_encode($texts),
            $resp->cost
        );
    }

    private function cosineSimilarity($vec1, $vec2)
    {
        $dot_product = 0.0;
        $vec1_magnitude = 0.0;
        $vec2_magnitude = 0.0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $vec1_magnitude += $vec1[$i] * $vec1[$i];
            $vec2_magnitude += $vec2[$i] * $vec2[$i];
        }

        $vec1_magnitude = sqrt($vec1_magnitude);
        $vec2_magnitude = sqrt($vec2_magnitude);

        if ($vec1_magnitude == 0.0 || $vec2_magnitude == 0.0) {
            return 0.0; // to handle division by zero
        }

        return $dot_product / ($vec1_magnitude * $vec2_magnitude);
    }
}
