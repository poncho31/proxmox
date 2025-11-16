<?php

namespace ComfyUI\Workflows;

/**
 * Workflow Face Swap
 * Échange de visage entre deux images
 */
class FaceSwapWorkflow extends BaseWorkflow
{
    private string $sourceImageName;
    private string $referenceImageName;
    private string $swapModel;
    private string $facesIndex;
    private string $referenceFacesIndex;

    public function __construct(
        string $sourceImageName,
        string $referenceImageName,
        string $swapModel = 'inswapper_128.onnx',
        string $facesIndex = '0',
        string $referenceFacesIndex = '0'
    ) {
        $this->sourceImageName = $sourceImageName;
        $this->referenceImageName = $referenceImageName;
        $this->swapModel = $swapModel;
        $this->facesIndex = $facesIndex;
        $this->referenceFacesIndex = $referenceFacesIndex;

        $this->buildNodes();
    }

    private function buildNodes(): void
    {
        // Node 1: LoadImage (Source)
        $this->nodes[] = $this->createNode(
            id: 1,
            type: 'LoadImage',
            pos: [100, 100],
            size: [315, 314],
            outputs: [
                $this->createOutput('IMAGE', 'IMAGE', [2], 0),
                $this->createOutput('MASK', 'MASK', [], 1)
            ],
            properties: ['Node name for S&R' => 'LoadImage'],
            widgetsValues: [$this->sourceImageName, 'image']
        );

        // Node 2: LoadImage (Reference)
        $this->nodes[] = $this->createNode(
            id: 2,
            type: 'LoadImage',
            pos: [100, 500],
            size: [315, 314],
            outputs: [
                $this->createOutput('IMAGE', 'IMAGE', [1], 0),
                $this->createOutput('MASK', 'MASK', [], 1)
            ],
            properties: ['Node name for S&R' => 'LoadImage'],
            widgetsValues: [$this->referenceImageName, 'image']
        );

        // Node 3: roop (Face Swap)
        $this->nodes[] = $this->createNode(
            id: 3,
            type: 'roop',
            pos: [500, 250],
            size: [315, 200],
            inputs: [
                $this->createInput('image', 'IMAGE', 2),
                $this->createInput('reference_image', 'IMAGE', 1)
            ],
            outputs: [
                $this->createOutput('IMAGE', 'IMAGE', [3], 0)
            ],
            properties: ['Node name for S&R' => 'roop'],
            widgetsValues: [
                $this->swapModel,
                $this->facesIndex,
                $this->referenceFacesIndex,
                1 // console_logging_level
            ]
        );

        // Node 5: ImageUpscaleWithModel
        $this->nodes[] = $this->createNode(
            id: 5,
            type: 'ImageUpscaleWithModel',
            pos: [900, 200],
            size: [241, 46],
            inputs: [
                $this->createInput('upscale_model', 'UPSCALE_MODEL', 5),
                $this->createInput('image', 'IMAGE', 3)
            ],
            outputs: [
                $this->createOutput('IMAGE', 'IMAGE', [4])
            ],
            properties: ['Node name for S&R' => 'ImageUpscaleWithModel']
        );

        // Node 6: UpscaleModelLoader
        $this->nodes[] = $this->createNode(
            id: 6,
            type: 'UpscaleModelLoader',
            pos: [600, 500],
            size: [315, 58],
            outputs: [
                $this->createOutput('UPSCALE_MODEL', 'UPSCALE_MODEL', [5])
            ],
            properties: ['Node name for S&R' => 'UpscaleModelLoader'],
            widgetsValues: ['RealESRGAN_x2plus.pth']
        );

        // Node 4: SaveImage
        $this->nodes[] = $this->createNode(
            id: 4,
            type: 'SaveImage',
            pos: [1200, 200],
            size: [315, 270],
            inputs: [
                $this->createInput('images', 'IMAGE', 4)
            ],
            properties: ['Node name for S&R' => 'SaveImage'],
            widgetsValues: ['FaceSwap']
        );

        // Create links
        $this->links = [
            $this->createLink(1, 2, 0, 3, 1, 'IMAGE'),
            $this->createLink(2, 1, 0, 3, 0, 'IMAGE'),
            $this->createLink(3, 3, 0, 5, 1, 'IMAGE'),
            $this->createLink(4, 5, 0, 4, 0, 'IMAGE'),
            $this->createLink(5, 6, 0, 5, 0, 'UPSCALE_MODEL')
        ];

        $this->lastNodeId = 6;
        $this->lastLinkId = 5;
    }

    public function getName(): string
    {
        return 'face_swap';
    }

    public function validate(): bool
    {
        return !empty($this->sourceImageName) &&
            !empty($this->referenceImageName) &&
            parent::validate();
    }
}
