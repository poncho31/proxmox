"""
ComfyUI Voice Modulator - Module de modulation vocale par IA
Supporte le pitch shifting et les presets de voix
Installation: Copier dans ComfyUI/custom_nodes/ComfyUI-TTS/
"""

import os
import torch
import torchaudio
import urllib.request

# Modèles disponibles pour téléchargement
AVAILABLE_MODELS = {
    "example_model": {
        "name": "Exemple (À remplacer)",
        "url": "https://example.com/model.pth",
        "description": "Ajoutez vos URLs dans ce dictionnaire",
    }
    # Ajoutez vos modèles ici !
}

class ModelDownloader:
    """Nœud pour télécharger et vérifier les modèles RVC"""

    @classmethod
    def INPUT_TYPES(cls):
        # Récupérer les modèles installés
        models_dir = cls.get_models_dir()
        installed = []
        if os.path.exists(models_dir):
            installed = [f.replace('.pth', '') for f in os.listdir(models_dir) if f.endswith('.pth')]

        # Liste des modèles disponibles au téléchargement
        available = list(AVAILABLE_MODELS.keys())

        return {
            "required": {
                "action": (["check_installed", "download_model"], {"default": "check_installed"}),
                "model_to_download": (available + ["aucun"], {"default": "aucun"}),
            }
        }

    RETURN_TYPES = ("STRING",)
    FUNCTION = "manage_models"
    CATEGORY = "audio/setup"
    OUTPUT_NODE = True

    @staticmethod
    def get_models_dir():
        """Obtient le chemin du dossier modèles"""
        # Essayer de trouver le dossier ComfyUI
        current = os.path.dirname(os.path.abspath(__file__))
        while current and os.path.basename(current) != "ComfyUI":
            parent = os.path.dirname(current)
            if parent == current:  # Racine atteinte
                break
            current = parent

        if os.path.basename(current) == "ComfyUI":
            return os.path.join(current, "models", "voice_models")
        return os.path.join("models", "voice_models")

    def manage_models(self, action, model_to_download):
        """Gère les modèles vocaux"""
        models_dir = self.get_models_dir()
        os.makedirs(models_dir, exist_ok=True)

        if action == "check_installed":
            # Vérifier les modèles installés
            if os.path.exists(models_dir):
                installed = [f for f in os.listdir(models_dir) if f.endswith('.pth')]
                if installed:
                    result = f"✅ {len(installed)} modèle(s) installé(s):\n"
                    for m in installed:
                        size = os.path.getsize(os.path.join(models_dir, m)) / (1024*1024)
                        result += f"  • {m} ({size:.1f} MB)\n"
                else:
                    result = "⚠️ Aucun modèle installé\n"
            else:
                result = "⚠️ Dossier modèles non trouvé\n"

            result += f"\n📂 Dossier: {models_dir}\n"
            result += f"\n💡 {len(AVAILABLE_MODELS)} modèle(s) disponible(s) au téléchargement"

            print(result)
            return (result,)

        elif action == "download_model":
            if model_to_download == "aucun":
                return ("❌ Sélectionnez un modèle à télécharger",)

            if model_to_download not in AVAILABLE_MODELS:
                return (f"❌ Modèle inconnu: {model_to_download}",)

            model_info = AVAILABLE_MODELS[model_to_download]
            model_path = os.path.join(models_dir, f"{model_to_download}.pth")

            if os.path.exists(model_path):
                return (f"⚠️ Modèle déjà installé: {model_to_download}.pth",)

            # Télécharger
            try:
                print(f"📥 Téléchargement: {model_info['name']}")
                print(f"🌐 URL: {model_info['url']}")

                def progress(block, block_size, total):
                    if total > 0:
                        pct = min(100, (block * block_size * 100) / total)
                        print(f"\r📥 {pct:.1f}%", end='', flush=True)

                urllib.request.urlretrieve(model_info['url'], model_path, progress)
                print(f"\n✅ Téléchargement terminé: {model_to_download}.pth")

                return (f"✅ Modèle installé avec succès!\n📂 {model_path}",)

            except Exception as e:
                return (f"❌ Erreur téléchargement: {str(e)}",)

        return ("❌ Action inconnue",)


class VoiceModulator_Simple:
    """Modulateur vocal simple avec pitch shifting et presets"""

    @classmethod
    def INPUT_TYPES(cls):
        return {
            "required": {
                "audio": ("AUDIO",),
                "pitch_shift": ("FLOAT", {
                    "default": 0.0,
                    "min": -12.0,
                    "max": 12.0,
                    "step": 0.5,
                    "display": "slider"
                }),
                "voice_preset": ([
                    "none",
                    "deeper_voice",
                    "higher_voice",
                    "robot_voice",
                    "child_voice",
                    "elderly_voice"
                ], {"default": "none"}),
            }
        }

    RETURN_TYPES = ("AUDIO",)
    FUNCTION = "apply_effects"
    CATEGORY = "audio/processing"

    def apply_effects(self, audio, pitch_shift, voice_preset):
        """Applique les effets vocaux"""
        waveform = audio.get("waveform", audio)
        sr = audio.get("sample_rate", 22050)

        if not isinstance(waveform, torch.Tensor):
            waveform = torch.tensor(waveform, dtype=torch.float32)

        # Appliquer les presets
        if voice_preset == "deeper_voice":
            pitch_shift = -3.0
        elif voice_preset == "higher_voice":
            pitch_shift = 3.0
        elif voice_preset == "robot_voice":
            pitch_shift = -1.0
        elif voice_preset == "child_voice":
            pitch_shift = 5.0
        elif voice_preset == "elderly_voice":
            pitch_shift = -2.0

        # Pitch shifting
        if pitch_shift != 0:
            ratio = 2 ** (pitch_shift / 12)
            new_sr = int(sr * ratio)
            resampler_up = torchaudio.transforms.Resample(sr, new_sr)
            resampler_down = torchaudio.transforms.Resample(new_sr, sr)
            waveform = resampler_down(resampler_up(waveform))

        return ({"waveform": waveform, "sample_rate": sr},)


class VoiceModulator_RVC:
    """Modulateur avec support RVC (si installé)"""

    def __init__(self):
        self.device = "cuda" if torch.cuda.is_available() else "cpu"

    @classmethod
    def INPUT_TYPES(cls):
        # Liste les modèles disponibles
        models_path = "models/voice_models"
        models = ["aucun"]
        if os.path.exists(models_path):
            models += [f for f in os.listdir(models_path) if f.endswith('.pth')]

        return {
            "required": {
                "audio": ("AUDIO",),
                "model": (models, {"default": "aucun"}),
                "pitch_shift": ("FLOAT", {
                    "default": 0.0,
                    "min": -12.0,
                    "max": 12.0,
                    "step": 0.5
                }),
            }
        }

    RETURN_TYPES = ("AUDIO",)
    FUNCTION = "modulate"
    CATEGORY = "audio/processing"

    def modulate(self, audio, model, pitch_shift):
        """Applique la modulation RVC si disponible, sinon pitch shift simple"""
        waveform = audio.get("waveform", audio)
        sr = audio.get("sample_rate", 22050)

        if not isinstance(waveform, torch.Tensor):
            waveform = torch.tensor(waveform, dtype=torch.float32)

        waveform = waveform.to(self.device)

        # Pitch shift
        if pitch_shift != 0:
            ratio = 2 ** (pitch_shift / 12)
            new_sr = int(sr * ratio)
            resampler_up = torchaudio.transforms.Resample(sr, new_sr)
            resampler_down = torchaudio.transforms.Resample(new_sr, sr)
            waveform = resampler_down(resampler_up(waveform))

        # TODO: Appliquer le modèle RVC si model != "aucun"
        # Nécessite l'installation de RVC

        return ({"waveform": waveform, "sample_rate": sr},)


# Enregistrement des nœuds
NODE_CLASS_MAPPINGS = {
    "ModelDownloader": ModelDownloader,
    "VoiceModulator_Simple": VoiceModulator_Simple,
    "VoiceModulator_RVC": VoiceModulator_RVC,
}

NODE_DISPLAY_NAME_MAPPINGS = {
    "ModelDownloader": "📦 Voice Models Manager",
    "VoiceModulator_Simple": "🎵 Voice Modulator (Simple)",
    "VoiceModulator_RVC": "🎙️ Voice Modulator (RVC)",
}
