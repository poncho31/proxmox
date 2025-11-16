<?php

/**
 * Exemples d'utilisation de l'API ComfyUI
 */

require_once __DIR__ . '/../src/api/comfyui/ComfyUIService.php';

use ComfyUI\ComfyUIService;

// Initialiser le service
$service = new ComfyUIService();

echo "=== Exemples d'utilisation de ComfyUI ===\n\n";

// ==========================================
// Exemple 1: Text to Image
// ==========================================
echo "1. Text to Image\n";
echo "----------------\n";

try {
    $result = $service->textToImage(
        prompt: 'A majestic lion in the savanna, golden hour lighting, photorealistic, 8k',
        negativePrompt: 'blurry, low quality, bad anatomy',
        width: 1024,
        height: 1024,
        steps: 8,
        cfg: 2.0
    );

    $promptId = $result['prompt_id'];
    echo "✓ Image generation started\n";
    echo "  Prompt ID: $promptId\n";

    // Attendre la fin de génération
    echo "  Waiting for completion...\n";
    $completion = $service->waitForCompletion($promptId, 120, 2);

    // Récupérer l'image
    if (isset($completion['outputs'])) {
        foreach ($completion['outputs'] as $nodeId => $output) {
            if (isset($output['images'])) {
                foreach ($output['images'] as $image) {
                    $filename = $image['filename'];
                    $imageData = $service->getImage($filename);

                    // Sauvegarder l'image
                    $savePath = __DIR__ . "/generated_$filename";
                    file_put_contents($savePath, $imageData);
                    echo "  ✓ Image saved: $savePath\n";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// Exemple 2: Chatbot
// ==========================================
echo "2. Chatbot\n";
echo "----------\n";

try {
    $result = $service->chatbot(
        userMessage: 'Explique-moi en 3 phrases ce qu\'est l\'intelligence artificielle',
        model: 'gemma2:2b',
        systemPrompt: 'Tu es un professeur pédagogue qui explique des concepts complexes simplement.',
        temperature: 0.7
    );

    $promptId = $result['prompt_id'];
    echo "✓ Chatbot query started\n";
    echo "  Prompt ID: $promptId\n";

    // Attendre la réponse
    echo "  Waiting for response...\n";
    $completion = $service->waitForCompletion($promptId, 60, 2);

    echo "  ✓ Response received\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// Exemple 3: Text to Speech
// ==========================================
echo "3. Text to Speech\n";
echo "-----------------\n";

try {
    $result = $service->textToSpeech(
        text: 'Bonjour, comment allez-vous aujourd\'hui ?',
        character: 'default',
        textTemperature: 0.7,
        filenamePrefix: 'voice_test_'
    );

    $promptId = $result['prompt_id'];
    echo "✓ TTS generation started\n";
    echo "  Prompt ID: $promptId\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// Exemple 4: Face Swap
// ==========================================
echo "4. Face Swap\n";
echo "------------\n";

try {
    // Note: Vous devez fournir des chemins d'images valides
    $sourceImage = __DIR__ . '/test_images/source.jpg';
    $referenceImage = __DIR__ . '/test_images/reference.jpg';

    if (file_exists($sourceImage) && file_exists($referenceImage)) {
        $result = $service->faceSwap(
            sourceImagePath: $sourceImage,
            referenceImagePath: $referenceImage,
            swapModel: 'inswapper_128.onnx'
        );

        $promptId = $result['prompt_id'];
        echo "✓ Face swap started\n";
        echo "  Prompt ID: $promptId\n";
    } else {
        echo "  ℹ Skipped: Test images not found\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// Exemple 5: Text to Video
// ==========================================
echo "5. Text to Video\n";
echo "----------------\n";

try {
    $result = $service->textToVideo(
        prompt: 'A bird flying gracefully over ocean waves at sunset',
        negativePrompt: 'cartoon, anime, 3d render',
        frames: 16,
        width: 512,
        height: 512,
        fps: 8.0
    );

    $promptId = $result['prompt_id'];
    echo "✓ Video generation started\n";
    echo "  Prompt ID: $promptId\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// Exemple 6: Image to Video
// ==========================================
echo "6. Image to Video\n";
echo "-----------------\n";

try {
    $sourceImage = __DIR__ . '/test_images/landscape.jpg';

    if (file_exists($sourceImage)) {
        $result = $service->imageToVideo(
            imagePath: $sourceImage,
            frames: 16,
            fps: 8.0
        );

        $promptId = $result['prompt_id'];
        echo "✓ Image to video conversion started\n";
        echo "  Prompt ID: $promptId\n";
    } else {
        echo "  ℹ Skipped: Test image not found\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ==========================================
// Informations utiles
// ==========================================
echo "=== Informations utiles ===\n\n";

// Workflows disponibles
echo "Workflows disponibles:\n";
$workflows = $service->getAvailableWorkflows();
foreach ($workflows as $name => $description) {
    echo "  - $name: $description\n";
}

echo "\n";

// Queue actuelle
echo "Queue actuelle:\n";
try {
    $queue = $service->getQueue();
    $running = count($queue['queue_running'] ?? []);
    $pending = count($queue['queue_pending'] ?? []);
    echo "  - En cours: $running\n";
    echo "  - En attente: $pending\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Fin des exemples ===\n";
