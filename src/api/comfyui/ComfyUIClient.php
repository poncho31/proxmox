<?php

namespace ComfyUI;

require_once __DIR__ . '/../../env.php';

/**
 * Client API pour ComfyUI
 * Gère les requêtes HTTP vers l'API ComfyUI
 */
class ComfyUIClient
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct()
    {
        \Env::load();

        $this->baseUrl = 'https://' . \Env::get('CADDY_MAIN_IP') . ':86';
        $this->username = \Env::get('CADDY_USER');
        $this->password = \Env::get('CADDY_PASSWORD');
    }

    /**
     * Exécute une requête HTTP vers l'API ComfyUI
     */
    public function request(string $endpoint, string $method = 'GET', ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);

        $headers = ['Content-Type: application/json'];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        if ($httpCode >= 400) {
            throw new \Exception("HTTP Error $httpCode: $response");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Upload une image vers ComfyUI
     */
    public function uploadImage(string $imagePath, bool $overwrite = false): array
    {
        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: $imagePath");
        }

        $url = $this->baseUrl . '/upload/image';

        $ch = curl_init($url);

        $cfile = new \CURLFile($imagePath, mime_content_type($imagePath), basename($imagePath));
        $postData = [
            'image' => $cfile,
            'overwrite' => $overwrite ? 'true' : 'false'
        ];

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Upload failed with HTTP $httpCode: $response");
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Envoie un workflow vers ComfyUI
     */
    public function queuePrompt(array $workflow): array
    {
        // Si le workflow contient déjà 'prompt' et 'client_id' (format API),
        // l'envoyer directement
        if (isset($workflow['prompt']) && isset($workflow['client_id'])) {
            return $this->request('/prompt', 'POST', $workflow);
        }

        // Sinon, wrapper dans 'prompt' (ancien format)
        return $this->request('/prompt', 'POST', ['prompt' => $workflow]);
    }

    /**
     * Récupère l'historique des tâches
     */
    public function getHistory(?string $promptId = null): array
    {
        $endpoint = $promptId ? "/history/$promptId" : '/history';
        return $this->request($endpoint);
    }

    /**
     * Récupère la queue des tâches en attente
     */
    public function getQueue(): array
    {
        return $this->request('/queue');
    }

    /**
     * Récupère une image générée
     */
    public function getImage(string $filename, string $subfolder = '', string $type = 'output'): string
    {
        $params = http_build_query([
            'filename' => $filename,
            'subfolder' => $subfolder,
            'type' => $type
        ]);

        $url = $this->baseUrl . '/view?' . $params;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $imageData = curl_exec($ch);
        curl_close($ch);

        return $imageData;
    }

    /**
     * Attend la fin d'exécution d'un prompt
     */
    public function waitForCompletion(string $promptId, int $maxWaitSeconds = 300, int $pollInterval = 2): ?array
    {
        $startTime = time();

        while ((time() - $startTime) < $maxWaitSeconds) {
            $history = $this->getHistory($promptId);

            if (!empty($history[$promptId])) {
                return $history[$promptId];
            }

            sleep($pollInterval);
        }

        throw new \Exception("Timeout waiting for prompt completion: $promptId");
    }

    /**
     * Annule une tâche en queue
     */
    public function cancelPrompt(string $promptId): array
    {
        return $this->request('/interrupt', 'POST', ['prompt_id' => $promptId]);
    }

    /**
     * Efface la queue
     */
    public function clearQueue(): array
    {
        return $this->request('/queue', 'POST', ['clear' => true]);
    }

    /**
     * Récupère les modèles disponibles
     */
    public function getModels(): array
    {
        return $this->request('/object_info');
    }
}
