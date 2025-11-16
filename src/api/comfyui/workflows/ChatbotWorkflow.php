<?php

namespace ComfyUI\Workflows;

/**
 * Workflow Chatbot avec Ollama
 */
class ChatbotWorkflow extends BaseWorkflow
{
    public function __construct(
        string $userMessage,
        string $model = 'gemma2:2b',
        string $systemPrompt = 'Tu es un assistant virtuel serviable et précis.',
        float $temperature = 0.7
    ) {
        $this->buildApiFormat([
            '1' => $this->createNode('OllamaChat', [
                'modele' => $model,
                'instructions_systeme' => $systemPrompt,
                'message_utilisateur' => $userMessage,
                'creativite' => $temperature
            ]),
            '2' => $this->createNode('ShowText|pysssss', [
                'text' => $this->nodeOutput('1')
            ])
        ]);
    }

    public function getName(): string
    {
        return 'chatbot';
    }
}
