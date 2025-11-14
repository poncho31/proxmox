"""
Bark TTS Simple - ComfyUI Node
Génération vocale ultra-réaliste avec Bark AI
Basé sur le script de test qui fonctionne
"""
import torch
import warnings

# Patcher torch.load pour compatibilité PyTorch 2.6+
original_torch_load = torch.load
def patched_torch_load(*args, **kwargs):
    kwargs['weights_only'] = False
    return original_torch_load(*args, **kwargs)
torch.load = patched_torch_load

# Désactiver warnings
warnings.filterwarnings('ignore', category=UserWarning)
warnings.filterwarnings('ignore', category=FutureWarning)

class BarkTTS:
    """Générateur de voix IA avec Bark - Simple et efficace"""

    # Liste COMPLÈTE des voix Bark (120 presets officiels)
    ALL_VOICES = [
        # === FRANÇAIS (10 voix) ===
        " FR Speaker 0|v2/fr_speaker_0",
        " FR Speaker 1|v2/fr_speaker_1",
        " FR Speaker 2|v2/fr_speaker_2",
        " FR Speaker 3|v2/fr_speaker_3",
        " FR Speaker 4|v2/fr_speaker_4",
        " FR Speaker 5|v2/fr_speaker_5",
        " FR Speaker 6|v2/fr_speaker_6",
        " FR Speaker 7|v2/fr_speaker_7",
        " FR Speaker 8|v2/fr_speaker_8",
        " FR Speaker 9|v2/fr_speaker_9",

        # === ANGLAIS (10 voix) ===
        " EN Speaker 0|v2/en_speaker_0",
        " EN Speaker 1|v2/en_speaker_1",
        " EN Speaker 2|v2/en_speaker_2",
        " EN Speaker 3|v2/en_speaker_3",
        " EN Speaker 4|v2/en_speaker_4",
        " EN Speaker 5|v2/en_speaker_5",
        " EN Speaker 6|v2/en_speaker_6",
        " EN Speaker 7|v2/en_speaker_7",
        " EN Speaker 8|v2/en_speaker_8",
        " EN Speaker 9|v2/en_speaker_9",

        # === ALLEMAND (10 voix) ===
        " DE Speaker 0|v2/de_speaker_0",
        " DE Speaker 1|v2/de_speaker_1",
        " DE Speaker 2|v2/de_speaker_2",
        " DE Speaker 3|v2/de_speaker_3",
        " DE Speaker 4|v2/de_speaker_4",
        " DE Speaker 5|v2/de_speaker_5",
        " DE Speaker 6|v2/de_speaker_6",
        " DE Speaker 7|v2/de_speaker_7",
        " DE Speaker 8|v2/de_speaker_8",
        " DE Speaker 9|v2/de_speaker_9",

        # === ESPAGNOL (10 voix) ===
        " ES Speaker 0|v2/es_speaker_0",
        " ES Speaker 1|v2/es_speaker_1",
        " ES Speaker 2|v2/es_speaker_2",
        " ES Speaker 3|v2/es_speaker_3",
        " ES Speaker 4|v2/es_speaker_4",
        " ES Speaker 5|v2/es_speaker_5",
        " ES Speaker 6|v2/es_speaker_6",
        " ES Speaker 7|v2/es_speaker_7",
        " ES Speaker 8|v2/es_speaker_8",
        " ES Speaker 9|v2/es_speaker_9",

        # === ITALIEN (10 voix) ===
        " IT Speaker 0|v2/it_speaker_0",
        " IT Speaker 1|v2/it_speaker_1",
        " IT Speaker 2|v2/it_speaker_2",
        " IT Speaker 3|v2/it_speaker_3",
        " IT Speaker 4|v2/it_speaker_4",
        " IT Speaker 5|v2/it_speaker_5",
        " IT Speaker 6|v2/it_speaker_6",
        " IT Speaker 7|v2/it_speaker_7",
        " IT Speaker 8|v2/it_speaker_8",
        " IT Speaker 9|v2/it_speaker_9",

        # === POLONAIS (10 voix) ===
        " PL Speaker 0|v2/pl_speaker_0",
        " PL Speaker 1|v2/pl_speaker_1",
        " PL Speaker 2|v2/pl_speaker_2",
        " PL Speaker 3|v2/pl_speaker_3",
        " PL Speaker 4|v2/pl_speaker_4",
        " PL Speaker 5|v2/pl_speaker_5",
        " PL Speaker 6|v2/pl_speaker_6",
        " PL Speaker 7|v2/pl_speaker_7",
        " PL Speaker 8|v2/pl_speaker_8",
        " PL Speaker 9|v2/pl_speaker_9",

        # === PORTUGAIS (10 voix) ===
        " PT Speaker 0|v2/pt_speaker_0",
        " PT Speaker 1|v2/pt_speaker_1",
        " PT Speaker 2|v2/pt_speaker_2",
        " PT Speaker 3|v2/pt_speaker_3",
        " PT Speaker 4|v2/pt_speaker_4",
        " PT Speaker 5|v2/pt_speaker_5",
        " PT Speaker 6|v2/pt_speaker_6",
        " PT Speaker 7|v2/pt_speaker_7",
        " PT Speaker 8|v2/pt_speaker_8",
        " PT Speaker 9|v2/pt_speaker_9",

        # === TURC (10 voix) ===
        " TR Speaker 0|v2/tr_speaker_0",
        " TR Speaker 1|v2/tr_speaker_1",
        " TR Speaker 2|v2/tr_speaker_2",
        " TR Speaker 3|v2/tr_speaker_3",
        " TR Speaker 4|v2/tr_speaker_4",
        " TR Speaker 5|v2/tr_speaker_5",
        " TR Speaker 6|v2/tr_speaker_6",
        " TR Speaker 7|v2/tr_speaker_7",
        " TR Speaker 8|v2/tr_speaker_8",
        " TR Speaker 9|v2/tr_speaker_9",

        # === HINDI (10 voix) ===
        " HI Speaker 0|v2/hi_speaker_0",
        " HI Speaker 1|v2/hi_speaker_1",
        " HI Speaker 2|v2/hi_speaker_2",
        " HI Speaker 3|v2/hi_speaker_3",
        " HI Speaker 4|v2/hi_speaker_4",
        " HI Speaker 5|v2/hi_speaker_5",
        " HI Speaker 6|v2/hi_speaker_6",
        " HI Speaker 7|v2/hi_speaker_7",
        " HI Speaker 8|v2/hi_speaker_8",
        " HI Speaker 9|v2/hi_speaker_9",

        # === CHINOIS (10 voix) ===
        " ZH Speaker 0|v2/zh_speaker_0",
        " ZH Speaker 1|v2/zh_speaker_1",
        " ZH Speaker 2|v2/zh_speaker_2",
        " ZH Speaker 3|v2/zh_speaker_3",
        " ZH Speaker 4|v2/zh_speaker_4",
        " ZH Speaker 5|v2/zh_speaker_5",
        " ZH Speaker 6|v2/zh_speaker_6",
        " ZH Speaker 7|v2/zh_speaker_7",
        " ZH Speaker 8|v2/zh_speaker_8",
        " ZH Speaker 9|v2/zh_speaker_9",

        # === JAPONAIS (10 voix) ===
        " JA Speaker 0|v2/ja_speaker_0",
        " JA Speaker 1|v2/ja_speaker_1",
        " JA Speaker 2|v2/ja_speaker_2",
        " JA Speaker 3|v2/ja_speaker_3",
        " JA Speaker 4|v2/ja_speaker_4",
        " JA Speaker 5|v2/ja_speaker_5",
        " JA Speaker 6|v2/ja_speaker_6",
        " JA Speaker 7|v2/ja_speaker_7",
        " JA Speaker 8|v2/ja_speaker_8",
        " JA Speaker 9|v2/ja_speaker_9",

        # === CORÉEN (10 voix) ===
        " KO Speaker 0|v2/ko_speaker_0",
        " KO Speaker 1|v2/ko_speaker_1",
        " KO Speaker 2|v2/ko_speaker_2",
        " KO Speaker 3|v2/ko_speaker_3",
        " KO Speaker 4|v2/ko_speaker_4",
        " KO Speaker 5|v2/ko_speaker_5",
        " KO Speaker 6|v2/ko_speaker_6",
        " KO Speaker 7|v2/ko_speaker_7",
        " KO Speaker 8|v2/ko_speaker_8",
        " KO Speaker 9|v2/ko_speaker_9",
    ]

    def __init__(self):
        self.models_loaded = False

    @classmethod
    def INPUT_TYPES(cls):
        return {
            "required": {
                "text": ("STRING", {
                    "default": "Bonjour, je suis une voix générée par Bark AI.",
                    "multiline": True
                }),
                "voice": (cls.ALL_VOICES, {
                    "default": " FR Speaker 1|v2/fr_speaker_1"
                }),
            }
        }

    RETURN_TYPES = ("AUDIO",)
    FUNCTION = "generate"
    CATEGORY = "audio"

    def generate(self, text, voice):
        """Génère l'audio avec Bark"""
        try:
            from bark import SAMPLE_RATE, generate_audio, preload_models
            import numpy as np
        except ImportError:
            raise RuntimeError(" Bark non installé. Installez avec: pip install git+https://github.com/suno-ai/bark.git")

        # Extraire l'ID de la voix
        voice_id = voice.split("|")[1] if "|" in voice else voice
        voice_name = voice.split("|")[0] if "|" in voice else voice

        # Charger les modèles (une seule fois)
        if not self.models_loaded:
            print(" Chargement modèles Bark...")
            preload_models()
            self.models_loaded = True
            print(" Modèles chargés")

        # Générer l'audio
        print(f" Génération: {voice_name}")
        audio_array = generate_audio(text, history_prompt=voice_id)

        # Convertir en format ComfyUI
        audio = audio_array.astype(np.float32)
        waveform = torch.from_numpy(audio).unsqueeze(0).unsqueeze(0)

        print(f" Audio généré: {len(audio)/SAMPLE_RATE:.1f}s")

        return ({"waveform": waveform, "sample_rate": SAMPLE_RATE},)

# Export pour ComfyUI
NODE_CLASS_MAPPINGS = {
    "BarkTTS": BarkTTS,
}

NODE_DISPLAY_NAME_MAPPINGS = {
    "BarkTTS": " Bark TTS",
}
