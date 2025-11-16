<?php

namespace ComfyUI\Workflows;

/**
 * Workflow Text to Speech
 * Convertit du texte en audio
 */
class TextToSpeechWorkflow extends BaseWorkflow
{
    private string $text;
    private string $character;
    private float $textTemperature;
    private string $filenamePrefix;

    public function __construct(
        string $text,
        string $character = 'default',
        float $textTemperature = 0.7,
        string $filenamePrefix = 'voice_character_'
    ) {
        $this->text = $text;
        $this->character = $character;
        $this->textTemperature = $textTemperature;
        $this->filenamePrefix = $filenamePrefix;

        $this->buildNodes();
    }

    private function buildNodes(): void
    {
        // Node 1: VoiceCharacter
        $this->nodes[] = $this->createNode(
            id: 1,
            type: 'VoiceCharacter',
            pos: [50.74, 30.64],
            size: [400, 400],
            outputs: [
                $this->createOutput('audio', 'AUDIO', [2])
            ],
            properties: ['Node name for S&R' => 'VoiceCharacter'],
            widgetsValues: [
                $this->text,
                $this->character,
                $this->textTemperature
            ]
        );

        // Node 3: SaveAudio
        $this->nodes[] = $this->createNode(
            id: 3,
            type: 'SaveAudio',
            pos: [500, 200],
            size: [315, 122],
            inputs: [
                $this->createInput('audio', 'AUDIO', 2)
            ],
            properties: ['Node name for S&R' => 'SaveAudio'],
            widgetsValues: [$this->filenamePrefix]
        );

        // Create links
        $this->links = [
            $this->createLink(2, 1, 0, 3, 0, 'AUDIO')
        ];

        $this->lastNodeId = 3;
        $this->lastLinkId = 2;
    }

    public function getName(): string
    {
        return 'text_to_speech';
    }

    public function validate(): bool
    {
        return !empty($this->text) && parent::validate();
    }
}
