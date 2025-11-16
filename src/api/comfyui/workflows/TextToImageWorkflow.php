<?php

namespace ComfyUI\Workflows;

/**
 * Workflow Text to Image
 * Génère une image à partir d'un texte
 */
class TextToImageWorkflow extends BaseWorkflow
{
    private string $prompt;
    private string $negativePrompt;
    private string $checkpoint;
    private int $width;
    private int $height;
    private int $steps;
    private float $cfg;
    private int $seed;

    public function __construct(
        string $prompt,
        string $negativePrompt = '',
        string $checkpoint = 'realvisxlV50_v50LightningBakedvae.safetensors',
        int $width = 1024,
        int $height = 1024,
        int $steps = 8,
        float $cfg = 2.0,
        ?int $seed = null
    ) {
        $this->prompt = $prompt;
        $this->negativePrompt = $negativePrompt ?: 'blurry, low quality, bad anatomy, distorted';
        $this->checkpoint = $checkpoint;
        $this->width = $width;
        $this->height = $height;
        $this->steps = $steps;
        $this->cfg = $cfg;
        $this->seed = $seed ?? rand(0, 999999999);

        $this->buildNodes();
    }

    private function buildNodes(): void
    {
        // Node 0: CheckpointLoaderSimple
        $this->nodes[] = $this->createNode(
            id: 0,
            type: 'CheckpointLoaderSimple',
            pos: [100, 130],
            size: [270, 98],
            outputs: [
                $this->createOutput('MODEL', 'MODEL', [1], 0),
                $this->createOutput('CLIP', 'CLIP', [2, 3], 1),
                $this->createOutput('VAE', 'VAE', [4], 2)
            ],
            properties: ['Node name for S&R' => 'CheckpointLoaderSimple'],
            widgetsValues: [$this->checkpoint]
        );

        // Node 1: KSampler
        $this->nodes[] = $this->createNode(
            id: 1,
            type: 'KSampler',
            pos: [800, 200],
            size: [315, 262],
            inputs: [
                $this->createInput('model', 'MODEL', 1),
                $this->createInput('positive', 'CONDITIONING', 2),
                $this->createInput('negative', 'CONDITIONING', 3),
                $this->createInput('latent_image', 'LATENT', 5)
            ],
            outputs: [
                $this->createOutput('LATENT', 'LATENT', [4])
            ],
            properties: ['Node name for S&R' => 'KSampler'],
            widgetsValues: [
                $this->seed,
                'fixed',
                $this->steps,
                $this->cfg,
                'euler',
                'normal',
                1.0
            ]
        );

        // Node 2: CLIPTextEncode (Positive)
        $this->nodes[] = $this->createNode(
            id: 2,
            type: 'CLIPTextEncode',
            pos: [400, 100],
            size: [400, 200],
            inputs: [
                $this->createInput('clip', 'CLIP', 2)
            ],
            outputs: [
                $this->createOutput('CONDITIONING', 'CONDITIONING', [2])
            ],
            properties: ['Node name for S&R' => 'CLIPTextEncode'],
            widgetsValues: [$this->prompt]
        );

        // Node 3: CLIPTextEncode (Negative)
        $this->nodes[] = $this->createNode(
            id: 3,
            type: 'CLIPTextEncode',
            pos: [400, 350],
            size: [400, 200],
            inputs: [
                $this->createInput('clip', 'CLIP', 3)
            ],
            outputs: [
                $this->createOutput('CONDITIONING', 'CONDITIONING', [3])
            ],
            properties: ['Node name for S&R' => 'CLIPTextEncode'],
            widgetsValues: [$this->negativePrompt]
        );

        // Node 4: VAEDecode
        $this->nodes[] = $this->createNode(
            id: 4,
            type: 'VAEDecode',
            pos: [1200, 200],
            size: [210, 46],
            inputs: [
                $this->createInput('samples', 'LATENT', 4),
                $this->createInput('vae', 'VAE', 4)
            ],
            outputs: [
                $this->createOutput('IMAGE', 'IMAGE', [6])
            ],
            properties: ['Node name for S&R' => 'VAEDecode']
        );

        // Node 5: EmptyLatentImage
        $this->nodes[] = $this->createNode(
            id: 5,
            type: 'EmptyLatentImage',
            pos: [400, 600],
            size: [315, 106],
            outputs: [
                $this->createOutput('LATENT', 'LATENT', [5])
            ],
            properties: ['Node name for S&R' => 'EmptyLatentImage'],
            widgetsValues: [$this->width, $this->height, 1]
        );

        // Node 6: SaveImage
        $this->nodes[] = $this->createNode(
            id: 6,
            type: 'SaveImage',
            pos: [1500, 200],
            size: [315, 270],
            inputs: [
                $this->createInput('images', 'IMAGE', 6)
            ],
            properties: ['Node name for S&R' => 'SaveImage'],
            widgetsValues: ['ComfyUI']
        );

        // Create links
        $this->links = [
            $this->createLink(1, 0, 0, 1, 0, 'MODEL'),
            $this->createLink(2, 0, 1, 2, 0, 'CLIP'),
            $this->createLink(3, 0, 1, 3, 0, 'CLIP'),
            $this->createLink(4, 0, 2, 4, 1, 'VAE'),
            $this->createLink(5, 5, 0, 1, 3, 'LATENT'),
            $this->createLink(2, 2, 0, 1, 1, 'CONDITIONING'),
            $this->createLink(3, 3, 0, 1, 2, 'CONDITIONING'),
            $this->createLink(4, 1, 0, 4, 0, 'LATENT'),
            $this->createLink(6, 4, 0, 6, 0, 'IMAGE')
        ];

        $this->lastNodeId = 6;
        $this->lastLinkId = 6;
    }

    public function getName(): string
    {
        return 'text_to_image';
    }

    public function validate(): bool
    {
        return !empty($this->prompt) && parent::validate();
    }
}
