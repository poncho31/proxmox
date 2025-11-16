<?php

namespace ComfyUI;

require_once __DIR__ . '/ComfyUIClient.php';
require_once __DIR__ . '/workflows/WorkflowInterface.php';
require_once __DIR__ . '/workflows/BaseWorkflow.php';
require_once __DIR__ . '/workflows/TextToImageWorkflow.php';
require_once __DIR__ . '/workflows/ChatbotWorkflow.php';
require_once __DIR__ . '/workflows/TextToSpeechWorkflow.php';
require_once __DIR__ . '/workflows/FaceSwapWorkflow.php';
require_once __DIR__ . '/workflows/TextToVideoWorkflow.php';
require_once __DIR__ . '/workflows/ImageToVideoWorkflow.php';

use ComfyUI\Workflows\TextToImageWorkflow;
use ComfyUI\Workflows\ChatbotWorkflow;
use ComfyUI\Workflows\TextToSpeechWorkflow;
use ComfyUI\Workflows\FaceSwapWorkflow;
use ComfyUI\Workflows\TextToVideoWorkflow;
use ComfyUI\Workflows\ImageToVideoWorkflow;

/**
 * Service principal pour ComfyUI
 * Orchestre les workflows et les interactions avec l'API
 */
class ComfyUIService
{
    private ComfyUIClient $client;

    public function __construct()
    {
        $this->client = new ComfyUIClient();
    }

    /**
     * Génère une image à partir d'un texte
     */
    public function textToImage(
        string $prompt,
        string $negativePrompt = '',
        string $checkpoint = 'realvisxlV50_v50LightningBakedvae.safetensors',
        int $width = 1024,
        int $height = 1024,
        int $steps = 8,
        float $cfg = 2.0,
        ?int $seed = null
    ): array {
        $workflow = new TextToImageWorkflow(
            $prompt,
            $negativePrompt,
            $checkpoint,
            $width,
            $height,
            $steps,
            $cfg,
            $seed
        );

        if (!$workflow->validate()) {
            throw new \Exception('Invalid workflow parameters');
        }

        return $this->client->queuePrompt($workflow->build());
    }

    /**
     * Chatbot avec Ollama
     */
    public function chatbot(
        string $userMessage,
        string $model = 'gemma2:2b',
        string $systemPrompt = 'Tu es un assistant virtuel serviable et précis.',
        float $temperature = 0.7
    ): array {
        $workflow = new ChatbotWorkflow(
            $userMessage,
            $model,
            $systemPrompt,
            $temperature
        );

        if (!$workflow->validate()) {
            throw new \Exception('Invalid workflow parameters');
        }

        return $this->client->queuePrompt($workflow->build());
    }

    /**
     * Text to Speech
     */
    public function textToSpeech(
        string $text,
        string $character = 'default',
        float $textTemperature = 0.7,
        string $filenamePrefix = 'voice_character_'
    ): array {
        $workflow = new TextToSpeechWorkflow(
            $text,
            $character,
            $textTemperature,
            $filenamePrefix
        );

        if (!$workflow->validate()) {
            throw new \Exception('Invalid workflow parameters');
        }

        return $this->client->queuePrompt($workflow->build());
    }

    /**
     * Face Swap
     */
    public function faceSwap(
        string $sourceImagePath,
        string $referenceImagePath,
        string $swapModel = 'inswapper_128.onnx',
        string $facesIndex = '0',
        string $referenceFacesIndex = '0'
    ): array {
        // Upload des images
        $sourceUpload = $this->client->uploadImage($sourceImagePath);
        $referenceUpload = $this->client->uploadImage($referenceImagePath);

        $workflow = new FaceSwapWorkflow(
            $sourceUpload['name'],
            $referenceUpload['name'],
            $swapModel,
            $facesIndex,
            $referenceFacesIndex
        );

        if (!$workflow->validate()) {
            throw new \Exception('Invalid workflow parameters');
        }

        return $this->client->queuePrompt($workflow->build());
    }

    /**
     * Text to Video
     */
    public function textToVideo(
        string $prompt,
        string $negativePrompt = 'cartoon, anime, 3d render, cgi, fake, painting, sketch',
        int $frames = 16,
        int $width = 512,
        int $height = 512,
        float $fps = 8.0,
        string $checkpoint = 'realvisxlV50_v50LightningBakedvae.safetensors',
        int $steps = 20,
        float $cfg = 7.0,
        ?int $seed = null
    ): array {
        $workflow = new TextToVideoWorkflow(
            $prompt,
            $negativePrompt,
            $frames,
            $width,
            $height,
            $fps,
            $checkpoint,
            $steps,
            $cfg,
            $seed
        );

        if (!$workflow->validate()) {
            throw new \Exception('Invalid workflow parameters');
        }

        return $this->client->queuePrompt($workflow->build());
    }

    /**
     * Image to Video
     */
    public function imageToVideo(
        string $imagePath,
        int $frames = 16,
        float $fps = 8.0,
        string $checkpoint = 'realvisxlV50_v50LightningBakedvae.safetensors',
        int $motionStrength = 127
    ): array {
        // Upload de l'image
        $imageUpload = $this->client->uploadImage($imagePath);

        $workflow = new ImageToVideoWorkflow(
            $imageUpload['name'],
            $frames,
            $fps,
            $checkpoint,
            $motionStrength
        );

        if (!$workflow->validate()) {
            throw new \Exception('Invalid workflow parameters');
        }

        return $this->client->queuePrompt($workflow->build());
    }

    /**
     * Upload une image
     */
    public function uploadImage(string $imagePath, bool $overwrite = false): array
    {
        return $this->client->uploadImage($imagePath, $overwrite);
    }

    /**
     * Récupère l'historique
     */
    public function getHistory(?string $promptId = null): array
    {
        return $this->client->getHistory($promptId);
    }

    /**
     * Récupère la queue
     */
    public function getQueue(): array
    {
        return $this->client->getQueue();
    }

    /**
     * Récupère une image générée
     */
    public function getImage(string $filename, string $subfolder = '', string $type = 'output'): string
    {
        return $this->client->getImage($filename, $subfolder, $type);
    }

    /**
     * Attend la fin d'exécution d'un prompt
     */
    public function waitForCompletion(string $promptId, int $maxWaitSeconds = 300, int $pollInterval = 2): ?array
    {
        return $this->client->waitForCompletion($promptId, $maxWaitSeconds, $pollInterval);
    }

    /**
     * Annule un prompt
     */
    public function cancelPrompt(string $promptId): array
    {
        return $this->client->cancelPrompt($promptId);
    }

    /**
     * Efface la queue
     */
    public function clearQueue(): array
    {
        return $this->client->clearQueue();
    }

    /**
     * Récupère les modèles disponibles
     */
    public function getModels(): array
    {
        return $this->client->getModels();
    }

    /**
     * Workflows disponibles
     */
    public function getAvailableWorkflows(): array
    {
        return [
            'text_to_image' => 'Génération d\'image à partir d\'un texte',
            'chatbot' => 'Chatbot avec Ollama',
            'text_to_speech' => 'Synthèse vocale',
            'face_swap' => 'Échange de visage',
            'text_to_video' => 'Génération de vidéo à partir d\'un texte',
            'image_to_video' => 'Conversion d\'image en vidéo'
        ];
    }
}
