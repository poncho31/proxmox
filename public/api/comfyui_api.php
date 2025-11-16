<?php

/**
 * API REST pour ComfyUI
 * Endpoints pour interagir avec ComfyUI via la classe ComfyUIService
 */

require_once __DIR__ . '/../../src/api/comfyui/ComfyUIService.php';

use ComfyUI\ComfyUIService;

header('Content-Type: application/json');

// Gestion CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $service = new ComfyUIService();

    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_GET['endpoint'] ?? '';

    // Router
    switch ($path) {

        // ==========================================
        // TEXT TO IMAGE
        // ==========================================
        case 'text-to-image':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $service->textToImage(
                prompt: $data['prompt'] ?? 'A beautiful landscape',
                negativePrompt: $data['negative_prompt'] ?? '',
                checkpoint: $data['checkpoint'] ?? 'realvisxlV50_v50LightningBakedvae.safetensors',
                width: intval($data['width'] ?? 1024),
                height: intval($data['height'] ?? 1024),
                steps: intval($data['steps'] ?? 8),
                cfg: floatval($data['cfg'] ?? 2.0),
                seed: isset($data['seed']) ? intval($data['seed']) : null
            );

            echo json_encode([
                'success' => true,
                'prompt_id' => $result['prompt_id'] ?? null,
                'data' => $result
            ]);
            break;

        // ==========================================
        // CHATBOT
        // ==========================================
        case 'chatbot':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $service->chatbot(
                userMessage: $data['message'] ?? 'Bonjour!',
                model: $data['model'] ?? 'gemma2:2b',
                systemPrompt: $data['system_prompt'] ?? 'Tu es un assistant virtuel serviable et précis.',
                temperature: floatval($data['temperature'] ?? 0.7)
            );

            echo json_encode([
                'success' => true,
                'prompt_id' => $result['prompt_id'] ?? null,
                'data' => $result
            ]);
            break;

        // ==========================================
        // TEXT TO SPEECH
        // ==========================================
        case 'text-to-speech':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $service->textToSpeech(
                text: $data['text'] ?? 'Bonjour',
                character: $data['character'] ?? 'default',
                textTemperature: floatval($data['temperature'] ?? 0.7),
                filenamePrefix: $data['filename_prefix'] ?? 'voice_character_'
            );

            echo json_encode([
                'success' => true,
                'prompt_id' => $result['prompt_id'] ?? null,
                'data' => $result
            ]);
            break;

        // ==========================================
        // FACE SWAP
        // ==========================================
        case 'face-swap':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            // Upload des fichiers
            if (!isset($_FILES['source_image']) || !isset($_FILES['reference_image'])) {
                throw new Exception('Missing image files', 400);
            }

            $sourceImagePath = $_FILES['source_image']['tmp_name'];
            $referenceImagePath = $_FILES['reference_image']['tmp_name'];

            $result = $service->faceSwap(
                sourceImagePath: $sourceImagePath,
                referenceImagePath: $referenceImagePath,
                swapModel: $_POST['swap_model'] ?? 'inswapper_128.onnx',
                facesIndex: $_POST['faces_index'] ?? '0',
                referenceFacesIndex: $_POST['reference_faces_index'] ?? '0'
            );

            echo json_encode([
                'success' => true,
                'prompt_id' => $result['prompt_id'] ?? null,
                'data' => $result
            ]);
            break;

        // ==========================================
        // TEXT TO VIDEO
        // ==========================================
        case 'text-to-video':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $result = $service->textToVideo(
                prompt: $data['prompt'] ?? 'A beautiful landscape',
                negativePrompt: $data['negative_prompt'] ?? 'cartoon, anime, 3d render',
                frames: intval($data['frames'] ?? 16),
                width: intval($data['width'] ?? 512),
                height: intval($data['height'] ?? 512),
                fps: floatval($data['fps'] ?? 8.0)
            );

            echo json_encode([
                'success' => true,
                'prompt_id' => $result['prompt_id'] ?? null,
                'data' => $result
            ]);
            break;

        // ==========================================
        // IMAGE TO VIDEO
        // ==========================================
        case 'image-to-video':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            if (!isset($_FILES['image'])) {
                throw new Exception('Missing image file', 400);
            }

            $imagePath = $_FILES['image']['tmp_name'];

            $result = $service->imageToVideo(
                imagePath: $imagePath,
                frames: intval($_POST['frames'] ?? 16),
                fps: floatval($_POST['fps'] ?? 8.0)
            );

            echo json_encode([
                'success' => true,
                'prompt_id' => $result['prompt_id'] ?? null,
                'data' => $result
            ]);
            break;

        // ==========================================
        // GET HISTORY
        // ==========================================
        case 'history':
            $promptId = $_GET['prompt_id'] ?? null;
            $result = $service->getHistory($promptId);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        // ==========================================
        // GET QUEUE
        // ==========================================
        case 'queue':
            $result = $service->getQueue();

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        // ==========================================
        // CLEAR QUEUE
        // ==========================================
        case 'clear-queue':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $result = $service->clearQueue();

            echo json_encode([
                'success' => true,
                'message' => 'Queue cleared',
                'data' => $result
            ]);
            break;

        // ==========================================
        // CANCEL PROMPT
        // ==========================================
        case 'cancel':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $promptId = $data['prompt_id'] ?? null;

            if (!$promptId) {
                throw new Exception('Missing prompt_id', 400);
            }

            $result = $service->cancelPrompt($promptId);

            echo json_encode([
                'success' => true,
                'message' => 'Prompt cancelled',
                'data' => $result
            ]);
            break;

        // ==========================================
        // GET IMAGE
        // ==========================================
        case 'get-image':
            $filename = $_GET['filename'] ?? null;

            if (!$filename) {
                throw new Exception('Missing filename parameter', 400);
            }

            $subfolder = $_GET['subfolder'] ?? '';
            $type = $_GET['type'] ?? 'output';

            $imageData = $service->getImage($filename, $subfolder, $type);

            // Déterminer le type MIME
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageData);

            header('Content-Type: ' . $mimeType);
            echo $imageData;
            exit;

            // ==========================================
            // WAIT FOR COMPLETION
            // ==========================================
        case 'wait':
            $promptId = $_GET['prompt_id'] ?? null;

            if (!$promptId) {
                throw new Exception('Missing prompt_id parameter', 400);
            }

            $maxWait = intval($_GET['max_wait'] ?? 300);
            $pollInterval = intval($_GET['poll_interval'] ?? 2);

            $result = $service->waitForCompletion($promptId, $maxWait, $pollInterval);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        // ==========================================
        // GET AVAILABLE WORKFLOWS
        // ==========================================
        case 'workflows':
            $workflows = $service->getAvailableWorkflows();

            echo json_encode([
                'success' => true,
                'workflows' => $workflows
            ]);
            break;

        // ==========================================
        // GET MODELS
        // ==========================================
        case 'models':
            $models = $service->getModels();

            echo json_encode([
                'success' => true,
                'models' => $models
            ]);
            break;

        // ==========================================
        // DEFAULT / HELP
        // ==========================================
        default:
            echo json_encode([
                'success' => true,
                'message' => 'ComfyUI API',
                'endpoints' => [
                    'POST /api/comfyui_api.php?endpoint=text-to-image' => 'Generate image from text',
                    'POST /api/comfyui_api.php?endpoint=chatbot' => 'Chat with Ollama',
                    'POST /api/comfyui_api.php?endpoint=text-to-speech' => 'Convert text to speech',
                    'POST /api/comfyui_api.php?endpoint=face-swap' => 'Swap faces between images',
                    'POST /api/comfyui_api.php?endpoint=text-to-video' => 'Generate video from text',
                    'POST /api/comfyui_api.php?endpoint=image-to-video' => 'Convert image to video',
                    'GET /api/comfyui_api.php?endpoint=history' => 'Get task history',
                    'GET /api/comfyui_api.php?endpoint=queue' => 'Get current queue',
                    'POST /api/comfyui_api.php?endpoint=clear-queue' => 'Clear the queue',
                    'POST /api/comfyui_api.php?endpoint=cancel' => 'Cancel a prompt',
                    'GET /api/comfyui_api.php?endpoint=get-image&filename=xxx' => 'Get generated image',
                    'GET /api/comfyui_api.php?endpoint=wait&prompt_id=xxx' => 'Wait for completion',
                    'GET /api/comfyui_api.php?endpoint=workflows' => 'List available workflows',
                    'GET /api/comfyui_api.php?endpoint=models' => 'Get available models',
                ]
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
