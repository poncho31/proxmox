<?php

namespace ComfyUI\Workflows;

/**
 * Workflow Text to Image simplifié
 */
class TextToImageWorkflow extends BaseWorkflow
{
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
        $seed = $seed ?? rand(0, 0xFFFFFFFF);

        $this->buildApiFormat([
            '1' => $this->createNode('CheckpointLoaderSimple', [
                'ckpt_name' => $checkpoint
            ]),
            '2' => $this->createNode('CLIPTextEncode', [
                'text' => $prompt,
                'clip' => $this->nodeOutput('1', 1)
            ]),
            '3' => $this->createNode('CLIPTextEncode', [
                'text' => $negativePrompt,
                'clip' => $this->nodeOutput('1', 1)
            ]),
            '4' => $this->createNode('EmptyLatentImage', [
                'width' => $width,
                'height' => $height,
                'batch_size' => 1
            ]),
            '5' => $this->createNode('KSampler', [
                'model' => $this->nodeOutput('1', 0),
                'seed' => $seed,
                'steps' => $steps,
                'cfg' => $cfg,
                'sampler_name' => 'euler',
                'scheduler' => 'normal',
                'positive' => $this->nodeOutput('2'),
                'negative' => $this->nodeOutput('3'),
                'latent_image' => $this->nodeOutput('4'),
                'denoise' => 1.0
            ]),
            '6' => $this->createNode('VAEDecode', [
                'samples' => $this->nodeOutput('5'),
                'vae' => $this->nodeOutput('1', 2)
            ]),
            '7' => $this->createNode('SaveImage', [
                'images' => $this->nodeOutput('6'),
                'filename_prefix' => 'ComfyUI'
            ])
        ]);
    }

    public function getName(): string
    {
        return 'text_to_image';
    }
}
