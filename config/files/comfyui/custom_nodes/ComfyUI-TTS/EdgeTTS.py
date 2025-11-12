import os
import asyncio
import edge_tts
import sounddevice
import numpy
from pathlib import Path
from datetime import datetime
import json

class EdgeTTS_Speak_Text:
    """
    Use Edge TTS to generate natural speech from text
    """

    # Fichier pour stocker le compteur
    counter_file = None

    @classmethod
    def INPUT_TYPES(cls):
        return {
            "required": {
                "text": ("STRING", {"default": "Entrez votre texte ici", "multiline": True}),
                "voice": (["fr-FR-DeniseNeural", "fr-FR-HenriNeural", "fr-CA-SylvieNeural", "fr-CA-AntoineNeural"], {"default": "fr-FR-DeniseNeural"}),
                "rate": (["x-slow", "slow", "medium", "fast", "x-fast"], {"default": "medium"}),
                "output_mode": (["stream", "file", "both"], {"default": "stream"}),
                "output_folder": ("STRING", {"default": "H:\\IA\\ComfyUI_windows_portable_nvidia\\ComfyUI_windows_portable\\ComfyUI\\output"}),
                "filename_prefix": ("STRING", {"default": "audio_tts"}),
            },
        }

    RETURN_TYPES = ("STRING",)
    RETURN_NAMES = ("file_path",)
    FUNCTION = "execute"
    CATEGORY = "TTS"
    OUTPUT_NODE = True

    @classmethod
    def get_counter_file(cls):
        """Obtenir le chemin du fichier compteur"""
        if cls.counter_file is None:
            cls.counter_file = Path(os.path.dirname(__file__)) / "tts_counter.json"
        return cls.counter_file

    @classmethod
    def get_next_counter(cls, prefix: str):
        """Obtenir et incrémenter le compteur pour un préfixe donné"""
        counter_file = cls.get_counter_file()

        # Charger les compteurs existants
        counters = {}
        if counter_file.exists():
            try:
                with open(counter_file, 'r') as f:
                    counters = json.load(f)
            except:
                counters = {}

        # Incrémenter le compteur pour ce préfixe
        current = counters.get(prefix, 0) + 1
        counters[prefix] = current

        # Sauvegarder
        with open(counter_file, 'w') as f:
            json.dump(counters, f, indent=2)

        return current

    def execute(self, text: str, voice: str, rate: str, output_mode: str, output_folder: str, filename_prefix: str = "audio_tts"):
        # Mapper les vitesses
        rate_map = {
            "x-slow": "-50%",
            "slow": "-25%",
            "medium": "+0%",
            "fast": "+25%",
            "x-fast": "+50%"
        }

        # Déterminer le chemin du fichier
        if output_mode in ["file", "both"]:
            # Créer le dossier de sortie s'il n'existe pas
            output_dir = Path(output_folder)
            output_dir.mkdir(parents=True, exist_ok=True)

            # Générer un nom avec préfixe et compteur incrémenté
            counter = self.get_next_counter(filename_prefix)
            filename = f"{filename_prefix}{counter:05d}.mp3"
            audio_file = output_dir / filename
        else:
            # Mode stream uniquement - fichier temporaire
            audio_file = Path(os.path.dirname(__file__)) / "temp_audio.mp3"

        # Générer l'audio avec Edge TTS
        async def generate():
            communicate = edge_tts.Communicate(text, voice, rate=rate_map[rate])
            await communicate.save(str(audio_file))

        # Exécuter la génération (compatible avec une boucle existante)
        try:
            loop = asyncio.get_event_loop()
        except RuntimeError:
            loop = asyncio.new_event_loop()
            asyncio.set_event_loop(loop)

        if loop.is_running():
            # Si la boucle tourne déjà, créer une tâche
            import concurrent.futures
            with concurrent.futures.ThreadPoolExecutor() as executor:
                future = executor.submit(asyncio.run, generate())
                future.result()
        else:
            # Sinon, exécuter directement
            loop.run_until_complete(generate())

        # Lire le fichier audio avec sounddevice si mode stream ou both
        if output_mode in ["stream", "both"]:
            import soundfile as sf
            data, samplerate = sf.read(str(audio_file))
            sounddevice.play(data, samplerate, blocking=True)

        # Nettoyer si mode stream uniquement
        final_path = ""
        if output_mode == "stream":
            if audio_file.exists():
                audio_file.unlink()
        else:
            # Retourner le chemin du fichier sauvegardé
            final_path = str(audio_file.absolute())
            print(f"Audio saved to: {final_path}")

        return (final_path,)
NODE_CLASS_MAPPINGS = {
    "EdgeTTS_Speak_Text": EdgeTTS_Speak_Text,
}

NODE_DISPLAY_NAME_MAPPINGS = {
    "EdgeTTS_Speak_Text": "Edge TTS Speak Text",
}
