<?php

declare(strict_types=1);

namespace Ai\Infrastruture\Services\xAi;

use Ai\Domain\Completion\MessageServiceInterface;
use Ai\Domain\ValueObjects\Model;
use Ai\Domain\Entities\MessageEntity;
use Ai\Domain\ValueObjects\Call;
use Ai\Domain\ValueObjects\Chunk;
use Ai\Domain\ValueObjects\Quote;
use Ai\Infrastruture\Services\CostCalculator;
use Ai\Infrastruture\Services\Tools\KnowledgeBase;
use Ai\Infrastruture\Services\Tools\ToolCollection;
use Billing\Domain\ValueObjects\Count;
use Generator;
use Override;;

use Traversable;

class MessageService implements MessageServiceInterface
{
    private array $models = [
        'grok-beta',
    ];

    public function __construct(
        private Client $client,
        private CostCalculator $calc,
        private ToolCollection $tools,
    ) {}

    #[Override]
    public function supportsModel(Model $model): bool
    {
        return in_array($model->value, $this->models);
    }

    #[Override]
    public function getSupportedModels(): Traversable
    {
        foreach ($this->models as $model) {
            yield new Model($model);
        }
    }

    #[Override]
    public function generateMessage(
        Model $model,
        MessageEntity $message
    ): Generator {
        $inTokens = 0;
        $outTokens = 0;
        $toolCost = new Count(0);
        $files = [];

        $messages = $this->buildMessageHistory(
            $message,
            131072,
            $files
        );

        $body = [
            'messages' => $messages,
            'model' => $model->value,
            'stream' => true
        ];

        $tools = $this->getTools($message);
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        $resp = $this->client->sendRequest('POST', '/v1/chat/completions', $body);
        $stream = new StreamResponse($resp);

        $calls = [];
        $content = '';

        foreach ($stream as $data) {
            $inTokens = $data->usage->prompt_tokens ?? $inTokens;
            $outTokens = $data->usage->completion_tokens ?? $outTokens;

            $choice = $data->choices[0] ?? null;

            if (!$choice) {
                continue;
            }

            if (isset($choice->delta->content)) {
                $chunk = $choice->delta->content;
                $content .= $chunk;
                yield new Chunk($chunk);
            }

            if (isset($choice->delta->tool_calls)) {
                $calls = array_merge($calls, $choice->delta->tool_calls);
            }
        }

        if ($calls) {
            $body['messages'][] = [
                'role' => 'assistant',
                'content' => $content,
                'tool_calls' => $calls
            ];
        }

        $callAgain = false;

        $embeddings = [];
        if ($message->getAssistant()?->hasDataset()) {
            foreach ($message->getAssistant()->getDataset() as $unit) {
                $embeddings[] = $unit->getEmbedding();
            }
        }

        foreach ($calls as $call) {
            $tool = $this->tools->find($call->function->name);

            if (!$tool) {
                continue;
            }

            $arguments = json_decode($call->function->arguments, true);
            yield new Chunk(new Call($call->function->name, $arguments));

            $cr = $tool->call(
                $message->getConversation()->getUser(),
                $message->getConversation()->getWorkspace(),
                $arguments,
                $files,
                $embeddings
            );

            $toolCost =  new Count($cr->cost->value + $toolCost->value);

            if ($cr->item) {
                yield new Chunk($cr->item);
            }

            $body['messages'][] = [
                'role' => 'tool',
                'content' => $cr->content,
                'tool_call_id' => $call->id
            ];

            $callAgain = true;
        }

        if ($callAgain) {
            $callsInTokens = 0;
            $callsOutTokens = 0;

            $resp = $this->client->sendRequest('POST', '/v1/chat/completions', $body);
            $stream = new StreamResponse($resp);

            foreach ($stream as $data) {
                $callsInTokens = $data->usage->prompt_tokens ?? $callsInTokens;
                $callsOutTokens = $data->usage->completion_tokens ?? $callsOutTokens;

                $choice = $data->choices[0] ?? null;

                if (!$choice) {
                    continue;
                }

                if (isset($choice->delta->content)) {
                    yield new Chunk($choice->delta->content);
                }
            }

            $inTokens += $callsInTokens;
            $outTokens += $callsOutTokens;
        }

        $inputCost = $this->calc->calculate(
            $inTokens,
            $model,
            CostCalculator::INPUT
        );

        $outputCost = $this->calc->calculate(
            $outTokens,
            $model,
            CostCalculator::OUTPUT
        );

        return new Count($inputCost->value + $outputCost->value + $toolCost->value);
    }

    private function buildMessageHistory(
        MessageEntity $message,
        int $maxContextTokens,
        array &$files = [],
        int $maxMessages = 20
    ): array {
        $messages = [];
        $current = $message;
        $inputTokensCount = 0;

        while (true) {
            $file = $current->getFile();
            if ($file) {
                $files[] = $file;
            }

            if ($current->getContent()->value) {
                if ($current->getQuote()->value) {
                    array_unshift(
                        $messages,
                        $this->generateQuoteMessage($current->getQuote())
                    );
                }

                $content = [];
                $tokens = 0;

                $content[] = [
                    'type' => 'text',
                    'text' => $current->getContent()->value
                ];

                // Rough estimate of tokens. 0.75 is the average number of words per token
                $tokens += count(explode($current->getContent()->value, ' ')) / 0.75;

                if ($tokens + $inputTokensCount > $maxContextTokens) {
                    break;
                }

                $inputTokensCount += $tokens;

                array_unshift($messages, [
                    'role' => $current->getRole()->value,
                    'content' => $content
                ]);
            }

            if (count($messages) >= $maxMessages) {
                break;
            }

            if ($current->getParent()) {
                $current = $current->getParent();
                continue;
            }

            break;
        }

        $assistant = $message->getAssistant();
        if ($assistant) {
            if ($assistant->getInstructions()->value) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => $assistant->getInstructions()->value
                ]);
            }
        }

        if ($files) {
            $messages[] = [
                'role' => 'system',
                'content' => 'The user has uploaded some files.'
            ];
        }

        if ($message->getAssistant()?->hasDataset()) {
            $messages[] = [
                'role' => 'system',
                'content' => 'Knowledge base is available. Use the ' . KnowledgeBase::LOOKUP_KEY . ' tool to access the knowledge base.'
            ];
        }

        return $messages;
    }

    private function generateQuoteMessage(Quote $quote): array
    {
        return [
            'role' => 'system',
            'content' => 'The user is referring to this in particular:\n' . $quote->value
        ];
    }

    private function getTools(MessageEntity $message): array
    {
        $tools = [];

        foreach ($this->tools->getToolsForMessage($message) as $key => $tool) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $key,
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getDefinitions()
                ]
            ];
        }

        return $tools;
    }
}
