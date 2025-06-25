<?php

namespace App\Console\Commands;

use App\Actions\Chat\InitializeChatSession;
use App\Actions\Chat\ProcessChatMessage;
use App\Actions\User\SummarizeUserContext;
use Illuminate\Console\Command;

class WeatherChatCommand extends Command
{
    protected $signature = 'weather:chat
                           {--user= : User identifier for personalized sessions}
                           {--memories : Enable memory persistence}
                           {--summarize : Show summary of user context}
                           {--stream : Enable streaming responses}
                           {--no-save-on-exit : Disable conversation summarization on exit}';

    protected $description = 'Start an AI weather chatbot session';

    public function handle()
    {
        if ($this->option('summarize')) {
            return app(SummarizeUserContext::class)->execute(
                $this->option('user'),
                $this
            );
        }

        return $this->startChatSession();
    }

    private function startChatSession(): int
    {
        $this->info('<fg=blue>AI Weather Chatbot</>');
        $streamingEnabled = $this->option('stream');

        if ($streamingEnabled) {
            $this->info('Streaming mode enabled - responses will appear as they are generated');
        }

        $this->info('Ask me about the weather! Type "/exit" to quit.');
        $this->newLine();

        $session = app(InitializeChatSession::class)->execute(
            $this->option('user'),
            $this->option('memories'),
            $this
        );

        if (!$session) {
            return 1;
        }

        // Main chat loop
        $chatHistory = [];
        while (true) {
            $input = $this->ask('You');

            if (strtolower(trim($input)) === '/exit') {
                $this->handleExit($chatHistory, $session, $streamingEnabled);
                break;
            }

            $chatHistory[] = ['role' => 'user', 'content' => $input];

            $response = app(ProcessChatMessage::class)->execute(
                $chatHistory,
                $session['user'],
                $session['use_memories'],
                $this,
                $streamingEnabled
            );

            if ($response) {
                if (!$streamingEnabled) {
                    $this->info("Bot: {$response}");
                }
                $chatHistory[] = ['role' => 'assistant', 'content' => $response];
            }

            $this->newLine();
        }

        return 0;
    }

    private function handleExit(array $chatHistory, array $session, bool $streamingEnabled): void
    {
        $shouldSave = !$this->option('no-save-on-exit');

        if ($shouldSave && $session['use_memories'] && !empty($chatHistory)) {
            $this->info('<fg=yellow>Summarizing conversation...</>');

            try {
                $response = app(ProcessChatMessage::class)->execute(
                    array_merge($chatHistory, [[
                        'role' => 'user',
                        'content' => 'Please summarize our conversation and save any important information I shared using your memory tools. Focus on preferences, locations, or other details that would be useful to remember for future conversations.',
                    ]]),
                    $session['user'],
                    $session['use_memories'],
                    $this,
                    $streamingEnabled
                );

                if ($response && !$streamingEnabled) {
                    $this->info("Bot: {$response}");
                }

                $this->newLine();
                $this->info('<fg=green>Conversation summarized and saved!</>');
            } catch (\Exception $e) {
                $this->warn('<fg=red>Failed to summarize conversation.</>');
            }
        }

        $this->info('<fg=blue>Goodbye!</>');
    }
}
