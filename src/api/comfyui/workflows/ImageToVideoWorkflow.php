<?php

namespace ComfyUI\Workflows;

/**
 * Workflow Image to Video
 * Convertit une image en vidéo
 */
class ImageToVideoWorkflow extends BaseWorkflow
{
    private string $imageName;
    private int $frames;
    private float $fps;
    private string $checkpoint;
    private int $motionStrength;

    public function __construct(
        string $imageName,
        int $frames = 16,
        float $fps = 8.0,
        string $checkpoint = 'realvisxlV50_v50LightningBakedvae.safetensors',
        int $motionStrength = 127
    ) {
        $this->imageName = $imageName;
        $this->frames = $frames;
        $this->fps = $fps;
        $this->checkpoint = $checkpoint;
        $this->motionStrength = $motionStrength;

        $this->buildNodes();
    }

    private function buildNodes(): void
    {
        // Node 1: LoadImage
        $this->nodes[] = $this->createNode(
            id: 1,
            type: 'LoadImage',
            pos: [100, 100],
            size: [315, 314],
            outputs: [
                $this->createOutput('IMAGE', 'IMAGE', [1], 0),
                $this->createOutput('MASK', 'MASK', [], 1)
            ],
            properties: ['Node name for S&R' => 'LoadImage'],
            widgetsValues: [$this->imageName, 'image']
        );

        // Node 2: CheckpointLoaderSimple
        $this->nodes[] = $this->createNode(
            id: 2,
            type: 'CheckpointLoaderSimple',
            pos: [100, 500],
            size: [434, 117],
            outputs: [
                $this->createOutput('MODEL', 'MODEL', [2], 0),
                $this->createOutput('CLIP', 'CLIP', [3, 4], 1),
                $this->createOutput('VAE', 'VAE', [5, 6], 2)
            ],
            properties: ['Node name for S&R' => 'CheckpointLoaderSimple'],
            widgetsValues: [$this->checkpoint]
        );

        // Node 3: VAEEncode
        $this->nodes[] = $this->createNode(
            id: 3,
            type: 'VAEEncode',
            pos: [500, 100],
            size: [210, 46],
            inputs: [
                $this->createInput('pixels', 'IMAGE', 1),
                $this->createInput('vae', 'VAE', 5)
            ],
            outputs: [
                $this->createOutput('LATENT', 'LATENT', [7])
            ],
            properties: ['Node name for S&R' => 'VAEEncode']
        );

        // Node 4: LatentInterpolate (pour créer le mouvement)
        $this->nodes[] = $this->createNode(
            id: 4,
            type: 'LatentInterpolate',
            pos: [800, 100],
            size: [315, 78],
            inputs: [
                $this->createInput('samples1', 'LATENT', 7),
                $this->createInput('samples2', 'LATENT', 7)
            ],
            outputs: [
                $this->createOutput('LATENT', 'LATENT', [8])
            ],
            properties: ['Node name for S&R' => 'LatentInterpolate'],
            widgetsValues: [$this->frames, 'lerp']
        );

        // Node 5: VAEDecode
        $this->nodes[] = $this->createNode(
            id: 5,
            type: 'VAEDecode',
            pos: [1200, 100],
            size: [210, 46],
            inputs: [
                $this->createInput('samples', 'LATENT', 8),
                $this->createInput('vae', 'VAE', 6)
            ],
            outputs: [
                $this->createOutput('IMAGE', 'IMAGE', [9])
            ],
            properties: ['Node name for S&R' => 'VAEDecode']
        );

        // Node 6: SaveAnimatedWEBP
        $this->nodes[] = $this->createNode(
            id: 6,
            type: 'SaveAnimatedWEBP',
            pos: [1500, 100],
            size: [315, 150],
            inputs: [
                $this->createInput('images', 'IMAGE', 9)
            ],
            properties: ['Node name for S&R' => 'SaveAnimatedWEBP'],
            widgetsValues: [
                'ImageToVideo',
                $this->fps,
                'default',
                false,
                80,
                'default'
            ]
        );

        // Create links
        $this->links = [
            $this->createLink(1, 1, 0, 3, 0, 'IMAGE'),
            $this->createLink(2, 2, 0, 4, 0, 'MODEL'),
            $this->createLink(3, 2, 1, 3, 0, 'CLIP'),
            $this->createLink(4, 2, 1, 4, 0, 'CLIP'),
            $this->createLink(5, 2, 2, 3, 1, 'VAE'),
            $this->createLink(6, 2, 2, 5, 1, 'VAE'),
            $this->createLink(7, 3, 0, 4, 0, 'LATENT'),
            $this->createLink(8, 4, 0, 5, 0, 'LATENT'),
            $this->createLink(9, 5, 0, 6, 0, 'IMAGE')
        ];

        $this->lastNodeId = 6;
        $this->lastLinkId = 9;
    }

    public function getName(): string
    {
        return 'image_to_video';
    }

    public function validate(): bool
    {
        return !empty($this->imageName) && parent::validate();
    }
}
