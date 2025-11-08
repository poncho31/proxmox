#!/bin/bash
# chmod +x ./_load.sh ; ./_load.sh ai_model "Resume php language" false
message="${1:-say hello}"
stream="${2:-true}"

echo "Message: $message"
echo "Stream: $stream"

curl -N -X POST http://$TAILSCALE_IP:83/api/chat \
  -H "Content-Type: application/json" \
  -d "$(jq -n \
    --arg msg "$message" \
    --arg model "$AI_BASE_MODEL" \
    --argjson stream "$stream" \
    '{
      model: $model,
      messages: [
        {role: "user", content: $msg}
      ],
      stream: $stream
    }')"

