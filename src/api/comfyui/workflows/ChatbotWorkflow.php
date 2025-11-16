<?php

namespace ComfyUI\Workflows;

/**
 * Workflow Chatbot avec Ollama
 * Génère une réponse de chatbot
 */
class ChatbotWorkflow extends BaseWorkflow
{
    private string $model;
    private string $systemPrompt;
    private string $userMessage;
    private float $temperature;

    public function __construct(
        string $userMessage,
        string $model = 'gemma2:2b',
        string $systemPrompt = 'Tu es un assistant virtuel serviable et précis.',
        float $temperature = 0.7
    ) {
        $this->userMessage = $userMessage;
        $this->model = $model;
        $this->systemPrompt = $systemPrompt;
        $this->temperature = $temperature;

        $this->buildNodes();
    }

    private function buildNodes(): void
    {
        // Format API ComfyUI avec les vrais noms de paramètres français
        $this->apiFormat = [
            'prompt' => [
                '1' => [
                    'class_type' => 'OllamaChat',
                    'inputs' => [
                        'modele' => $this->model,
                        'instructions_systeme' => $this->systemPrompt,
                        'message_utilisateur' => $this->userMessage,
                        'creativite' => $this->temperature
                    ]
                ],
                '2' => [
                    'class_type' => 'ShowText|pysssss',
                    'inputs' => [
                        'text' => ['1', 0]
                    ]
                ]
            ],
            'client_id' => 'php-chatbot-' . uniqid()
        ];
    }

    public function getName(): string
    {
        return 'chatbot';
    }

    public function validate(): bool
    {
        return !empty($this->userMessage) && parent::validate();
    }
}
