<?php

namespace App\Actions\Chat;

use App\Enums\AnthropicModel;
use App\Models\User;
use App\Services\MemoryService;
use App\Services\SystemPromptBuilder;
use App\Tools\ForecastTool;
use App\Tools\LoadMemoryTool;
use App\Tools\RecordMemoryTool;
use App\Tools\WeatherTool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Prism;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class ProcessChatMessage
{
    public function execute(array $chatHistory, User $user, bool $useMemories, Command $console, bool $stream): ?string
    {
        $tools = [
            app(WeatherTool::class),
            app(ForecastTool::class),
        ];

        if ($useMemories) {
            $memoryService = app(MemoryService::class);
            $tools[] = new LoadMemoryTool($user, $memoryService);
            $tools[] = new RecordMemoryTool($user, $memoryService);
        }

        $lastUserMessage = end($chatHistory)['content'] ?? '';

        $messages = array_map(fn(array $message) => match ($message['role']) {
            'user' => new UserMessage($message['content']),
            'assistant' => new AssistantMessage($message['content']),
        }, $chatHistory);

        $prismProvider = env('PRISM_PROVIDER', 'anthropic');
        $prismProviderModel = env('PRISM_PROVIDER_MODEL', AnthropicModel::CLAUDE_3_5_HAIKU->value);

        try {
            $prism = Prism::text()
                ->using($prismProvider, $prismProviderModel)
                ->withMaxSteps(3)
                ->withSystemPrompt(app(SystemPromptBuilder::class)->build($user, $useMemories))
                ->withMessages($messages)
                ->withTools($tools);

            if ($stream) {
                return $this->handleStreamedResponse($prism, $user, $lastUserMessage, $useMemories, $console);
            }

            return $this->handleRegularResponse($prism, $user, $lastUserMessage, $useMemories, $console);

        } catch (\Exception $e) {
            $this->handleError($e, $user, $lastUserMessage, $console);

            return null;
        }
    }

    private function handleStreamedResponse(PendingRequest $prism, User $user, string $message, bool $useMemories, Command $console): ?string
    {
        $console->getOutput()->write('Bot: ');
        $spinner = ['|', '/', '-', '\\'];
        $i = 0;

        $stream = $prism->asStream();
        $currentStepText = '';
        $finalResponse = '';
        $isDisplayingText = false;
        $displayedLength = 0;

        foreach ($stream as $chunk) {
            if ($chunk->toolResults) {
                // Tool results - reset for new step
                if (!empty($currentStepText)) {
                    if ($isDisplayingText) {
                        $console->getOutput()->write("\r" . str_repeat(' ', $displayedLength + 10) . "\r");
                        $console->getOutput()->write('Bot: ');
                        $isDisplayingText = false;
                        $displayedLength = 0;
                    }
                }
                $currentStepText = '';
            }

            if ($chunk->text) {
                $currentStepText .= $chunk->text;

                // Check if current step text is being duplicated
                $deduplicatedText = $this->deduplicateText($currentStepText);

                // Only display new characters that haven't been shown yet
                if (strlen($deduplicatedText) > $displayedLength) {
                    if (!$isDisplayingText) {
                        // Clear spinner and start displaying text
                        $console->getOutput()->write("\rBot: ");
                        $isDisplayingText = true;
                        $displayedLength = 0;
                    }

                    // Display only the new part
                    $newText = substr($deduplicatedText, $displayedLength);
                    $console->getOutput()->write($newText);
                    $displayedLength = strlen($deduplicatedText);
                    $finalResponse = $deduplicatedText;
                }
            } else if (!$isDisplayingText) {
                $console->getOutput()->write("\rBot: " . $spinner[$i++ % count($spinner)]);
            }
        }

        $responseText = $this->deduplicateText(trim($finalResponse)) ?: "I've processed that using my tools.";

        return $responseText;
    }

    private function handleRegularResponse(PendingRequest $prism, User $user, string $message, bool $useMemories, Command $console): ?string
    {
        $placeholder = '█';
        $console->getOutput()->write($placeholder);

        $response = $prism->asText();
        $responseText = $response->text ?: "I've processed that using my tools.";

        $console->getOutput()->write("\r" . str_repeat(' ', mb_strlen($placeholder)) . "\r");

        return $responseText;
    }

    private function handleError(\Exception $e, User $user, string $message, Command $console): void
    {
        $console->getOutput()->write("\r" . str_repeat(' ', 50) . "\r");

        Log::error('Chat processing error', [
            'user_id' => $user->id,
            'message' => $message,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        if (config('app.debug')) {
            $console->error("DEBUG ERROR: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");

            return;
        }

        $console->error("I'm sorry, I encountered an error while processing your request. Please try again.");
    }

    private function deduplicateText(string $text): string
    {
        if (empty($text)) {
            return $text;
        }

        $length = strlen($text);

        // is exactly duplicated first half == second half?
        if ($length % 2 === 0) {
            $halfLength = $length / 2;
            $firstHalf = substr($text, 0, $halfLength);
            $secondHalf = substr($text, $halfLength);

            if ($firstHalf === $secondHalf) {
                return $firstHalf;
            }
        }

        return $text;
    }
}
