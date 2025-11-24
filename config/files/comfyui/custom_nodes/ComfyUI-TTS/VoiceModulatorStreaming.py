"""
Piper TTS - ComfyUI Node
Génération vocale rapide pour streaming avec Piper
"""
import subprocess
import os
import torch
import numpy as np
import tempfile
import wave
import urllib.request
import zipfile
import threading

# Détection du chemin Piper
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PIPER_DIR = os.path.join(BASE_DIR, "piper")
PIPER_EXE = os.path.join(PIPER_DIR, "piper.exe")

# Modèles Piper - Voix réalistes (qualité haute prioritaire)
FRENCH_MODELS = {
    # === FRANÇAIS - Toutes les voix VÉRIFIÉES ===
    "fr_FR-siwis-medium": "🇫🇷 Femme (Siwis) - Qualité moyenne ★★★★",
    "fr_FR-siwis-low": "🇫🇷 Femme (Siwis) - Rapide ★★★",
    "fr_FR-upmc-medium": "🇫🇷 Multi-locuteur (Jessica/Pierre) - Qualité moyenne ★★★★",
    "fr_FR-tom-medium": "🇫🇷 Homme (Tom) - Qualité moyenne ★★★★",
    "fr_FR-mls-medium": "🇫🇷 Multi-locuteur (125 voix) - Qualité moyenne ★★★★",
    "fr_FR-mls_1840-low": "🇫🇷 Homme (MLS 1840) - Rapide ★★★",
    "fr_FR-gilles-low": "🇫🇷 Homme (Gilles) - Rapide ★★★",

    # === ANGLAIS US - Haute qualité (voix très réalistes) ===
    "en_US-amy-medium": "🇺🇸 Femme (Amy) - Qualité moyenne ★★★★",
    "en_US-arctic-medium": "🇺🇸 Multi-locuteur (18 voix) - Qualité moyenne ★★★★",
    "en_US-hfc_female-medium": "🇺🇸 Femme (HFC) - Haute qualité ★★★★★",
    "en_US-hfc_male-medium": "🇺🇸 Homme (HFC) - Haute qualité ★★★★★",
    "en_US-lessac-medium": "🇺🇸 Femme (Lessac) - Qualité moyenne ★★★★",
    "en_US-libritts-high": "🇺🇸 Multi-locuteur (904 voix) - Très haute qualité ★★★★★",
    "en_US-ryan-high": "🇺🇸 Homme (Ryan) - Très haute qualité ★★★★★",
    "en_US-ryan-medium": "🇺🇸 Homme (Ryan) - Qualité moyenne ★★★★",
    "en_US-joe-medium": "🇺🇸 Homme (Joe) - Qualité moyenne ★★★★",
    "en_US-kristin-medium": "🇺🇸 Femme (Kristin) - Qualité moyenne ★★★★",
    "en_US-kusal-medium": "🇺🇸 Homme (Kusal) - Qualité moyenne ★★★★",
    "en_US-l2arctic-medium": "🇺🇸 Multi-accent (24 voix) - Qualité moyenne ★★★★",
    "en_US-norman-medium": "🇺🇸 Homme (Norman) - Qualité moyenne ★★★",

    # === ANGLAIS UK - Haute qualité ===
    "en_GB-alan-medium": "🇬🇧 Homme (Alan) - Qualité moyenne ★★★★",
    "en_GB-northern_english_male-medium": "🇬🇧 Homme (Nord) - Qualité moyenne ★★★",
    "en_GB-semaine-medium": "🇬🇧 Multi-locuteur (4 voix) - Qualité moyenne ★★★★",
    "en_GB-vctk-medium": "🇬🇧 Multi-locuteur (109 voix) - Qualité moyenne ★★★★",

    # === ESPAGNOL ===
    "es_ES-mls-medium": "🇪🇸 Multi-locuteur - Qualité moyenne ★★★★",
    "es_ES-carlfm-x_low": "🇪🇸 Homme (Carlfm) - Rapide ★★★",
    "es_ES-sharvard-medium": "🇪🇸 Homme (Sharvard) - Qualité moyenne ★★★",
    "es_ES-davefx-medium": "🇪🇸 Homme (Davefx) - Qualité moyenne ★★★★",
    "es_MX-ald-medium": "🇲🇽 Homme (Mexique) - Qualité moyenne ★★★",
    "es_MX-claude-high": "🇲🇽 Homme (Claude) - Haute qualité ★★★★",

    # === ALLEMAND - Haute qualité ===
    "de_DE-thorsten-high": "🇩🇪 Homme (Thorsten) - Très haute qualité ★★★★★",
    "de_DE-thorsten-medium": "🇩🇪 Homme (Thorsten) - Qualité moyenne ★★★★",
    "de_DE-eva_k-x_low": "🇩🇪 Femme (Eva) - Rapide ★★★",
    "de_DE-karlsson-low": "🇩🇪 Homme (Karlsson) - Rapide ★★★",
    "de_DE-kerstin-low": "🇩🇪 Femme (Kerstin) - Rapide ★★★",
    "de_DE-pavoque-low": "🇩🇪 Homme (Pavoque) - Rapide ★★★",
    "de_DE-ramona-low": "🇩🇪 Femme (Ramona) - Rapide ★★★",

    # === ITALIEN ===
    "it_IT-riccardo-x_low": "🇮🇹 Homme (Riccardo) - Rapide ★★★★",
    "it_IT-paola-medium": "🇮🇹 Femme (Paola) - Qualité moyenne ★★★★",

    # === PORTUGAIS ===
    "pt_BR-faber-medium": "🇧🇷 Homme (Brésil) - Qualité moyenne ★★★★",
    "pt_BR-edresson-low": "🇧🇷 Homme (Edresson) - Rapide ★★★",
    "pt_PT-tugao-medium": "🇵🇹 Homme (Portugal) - Qualité moyenne ★★★",

    # === POLONAIS ===
    "pl_PL-darkman-medium": "🇵🇱 Homme (Darkman) - Qualité moyenne ★★★",
    "pl_PL-mls_6892-low": "🇵🇱 Femme - Rapide ★★★",
    "pl_PL-mc_speech-medium": "🇵🇱 Homme (MC Speech) - Qualité moyenne ★★★",

    # === RUSSE ===
    "ru_RU-ruslan-medium": "🇷🇺 Homme (Ruslan) - Qualité moyenne ★★★",
    "ru_RU-denis-medium": "🇷🇺 Homme (Denis) - Qualité moyenne ★★★",

    # === NÉERLANDAIS ===
    "nl_NL-mls-medium": "🇳🇱 Multi-locuteur (52 voix) - Qualité moyenne ★★★",
    "nl_BE-nathalie-medium": "🇧🇪 Femme (Nathalie) - Qualité moyenne ★★★",
    "nl_NL-pim-medium": "🇳🇱 Homme (Pim) - Qualité moyenne ★★★",

    # === UKRAINIEN ===
    "uk_UA-lada-x_low": "🇺🇦 Femme (Lada) - Rapide ★★★",
    "uk_UA-ukrainian_tts-medium": "🇺🇦 Multi-locuteur (3 voix) - Qualité moyenne ★★★",

    # === AUTRES LANGUES ===
    "ca_ES-upc_ona-medium": "🏴 Catalan - Femme (Ona) - Qualité moyenne ★★★",
    "da_DK-talesyntese-medium": "🇩🇰 Danois - Qualité moyenne ★★★",
    "el_GR-rapunzelina-medium": "🇬🇷 Grec - Femme - Qualité moyenne ★★★",
    "fi_FI-harri-medium": "🇫🇮 Finnois - Homme (Harri) - Qualité moyenne ★★★",
    "is_IS-bui-medium": "🇮🇸 Islandais - Homme (Bui) - Qualité moyenne ★★★",
    "no_NO-talesyntese-medium": "🇳🇴 Norvégien - Qualité moyenne ★★★",
    "sv_SE-nst-medium": "🇸🇪 Suédois - Qualité moyenne ★★★",
    "tr_TR-fettah-medium": "🇹🇷 Turc - Homme (Fettah) - Qualité moyenne ★★★",
    "vi_VN-vais1000-medium": "🇻🇳 Vietnamien - Qualité moyenne ★★★",
    "ar_JO-kareem-medium": "🇯🇴 Arabe - Homme (Kareem) - Qualité moyenne ★★★",
    "zh_CN-huayan-medium": "🇨🇳 Chinois - Femme (Huayan) - Qualité moyenne ★★★★",
    "hi_IN-priyamvada-medium": "🇮🇳 Hindi - Femme (Priyamvada) - Qualité moyenne ★★★",
}

def download_piper():
    """Télécharge Piper si nécessaire"""
    if os.path.exists(PIPER_EXE):
        return True

    print("📥 Téléchargement Piper TTS...")

    try:
        import shutil
        os.makedirs(PIPER_DIR, exist_ok=True)

        # Télécharger Piper Windows
        piper_url = "https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_windows_amd64.zip"
        zip_path = os.path.join(PIPER_DIR, "piper.zip")

        urllib.request.urlretrieve(piper_url, zip_path)

        # Extraire dans un dossier temporaire
        temp_extract = os.path.join(PIPER_DIR, "_temp_extract")
        with zipfile.ZipFile(zip_path, 'r') as zip_ref:
            zip_ref.extractall(temp_extract)

        os.remove(zip_path)

        # Trouver le dossier contenant piper.exe et COPIER TOUS LES FICHIERS
        found = False
        for root, dirs, files in os.walk(temp_extract):
            if "piper.exe" in files:
                print(f"📦 Fichiers Piper trouvés dans: {root}")
                # Copier TOUS les fichiers (exe + dll) à la racine de PIPER_DIR
                for file in files:
                    src = os.path.join(root, file)
                    dst = os.path.join(PIPER_DIR, file)
                    shutil.copy2(src, dst)
                    print(f"   ✓ Copié: {file}")

                # Copier aussi les sous-dossiers (espeak-ng-data, etc.)
                for subdir in dirs:
                    src_dir = os.path.join(root, subdir)
                    dst_dir = os.path.join(PIPER_DIR, subdir)
                    if os.path.exists(dst_dir):
                        shutil.rmtree(dst_dir)
                    shutil.copytree(src_dir, dst_dir)
                    print(f"   ✓ Copié dossier: {subdir}")

                found = True
                break

        # Nettoyer le dossier temporaire
        shutil.rmtree(temp_extract)

        if not found or not os.path.exists(PIPER_EXE):
            raise Exception("piper.exe introuvable après extraction")

        print("✅ Piper installé avec toutes les DLL")
        return True
    except Exception as e:
        print(f"❌ Erreur téléchargement Piper: {e}")
        return False

def download_model(model_name):
    """Télécharge un modèle Piper si nécessaire"""
    model_file = os.path.join(PIPER_DIR, f"{model_name}.onnx")
    config_file = os.path.join(PIPER_DIR, f"{model_name}.onnx.json")

    if os.path.exists(model_file) and os.path.exists(config_file):
        return True

    print(f"📥 Téléchargement modèle {model_name}...")

    try:
        # Parser le nom du modèle: langue_REGION-voice-quality
        parts = model_name.split('-')
        lang_region = parts[0]  # ex: fr_FR, ru_RU, en_US
        voice = '-'.join(parts[1:-1])  # ex: siwis, denis, ryan
        quality = parts[-1]  # ex: medium, low, high

        # Extraire langue et région
        lang_family = lang_region.split('_')[0]  # ex: fr, ru, en

        # Construire URL HuggingFace: langue/langue_REGION/voice/quality/
        base_url = f"https://huggingface.co/rhasspy/piper-voices/resolve/main/{lang_family}/{lang_region}/{voice}/{quality}"

        # Télécharger .onnx
        onnx_url = f"{base_url}/{model_name}.onnx"
        urllib.request.urlretrieve(onnx_url, model_file)

        # Télécharger .json
        json_url = f"{base_url}/{model_name}.onnx.json"
        urllib.request.urlretrieve(json_url, config_file)

        print(f"✅ Modèle {model_name} installé")
        return True
    except Exception as e:
        print(f"❌ Erreur téléchargement modèle: {e}")
        return False


class PiperTTS:
    """Générateur TTS avec Piper - Optimisé pour le streaming"""

    @classmethod
    def INPUT_TYPES(cls):
        return {
            "required": {
                "text": ("STRING", {"multiline": True, "default": ""}),
                "voice": (list(FRENCH_MODELS.keys()), {"default": "fr_FR-siwis-medium"}),
                "sample_rate": ("INT", {"default": 22050, "min": 16000, "max": 48000}),
            }
        }

    RETURN_TYPES = ("AUDIO",)
    RETURN_NAMES = ("audio",)
    FUNCTION = "generate"
    CATEGORY = "audio/tts"

    def generate(self, text, voice, sample_rate):
        # Installer Piper si nécessaire
        if not os.path.exists(PIPER_EXE):
            if not download_piper():
                raise Exception("Impossible d'installer Piper")

        # Double vérification que piper.exe existe
        if not os.path.exists(PIPER_EXE):
            raise Exception(f"piper.exe introuvable: {PIPER_EXE}")

        # Télécharger le modèle si nécessaire
        if not download_model(voice):
            raise Exception(f"Impossible de télécharger le modèle {voice}")

        model_path = os.path.join(PIPER_DIR, f"{voice}.onnx")

        # Générer l'audio
        print(f"🎵 Génération avec Piper ({voice})...")
        print(f"📂 PIPER_EXE: {PIPER_EXE}")
        print(f"📂 Model: {model_path}")
        print(f"✓ piper.exe exists: {os.path.exists(PIPER_EXE)}")
        print(f"✓ model exists: {os.path.exists(model_path)}")
        print(f"✓ config exists: {os.path.exists(model_path + '.json')}")

        output_file = os.path.join(tempfile.gettempdir(), f"piper_output_{os.getpid()}.wav")

        try:
            # Piper génère directement un WAV
            # Utiliser shell=True pour Windows et chercher les DLL
            cmd = f'"{PIPER_EXE}" -m "{model_path}" -f "{output_file}"'
            print(f"🚀 Commande: {cmd}")

            result = subprocess.run(
                cmd,
                input=text,
                shell=True,
                capture_output=True,
                text=True,
                cwd=PIPER_DIR  # Important: exécuter depuis le dossier piper pour les DLL
            )

            print(f"📤 Return code: {result.returncode}")
            print(f"📤 STDOUT: {result.stdout}")
            print(f"📤 STDERR: {result.stderr}")

            if result.returncode != 0:
                raise Exception(f"Piper failed (code {result.returncode}): stdout={result.stdout}, stderr={result.stderr}")

            if not os.path.exists(output_file):
                raise Exception(f"Le fichier audio n'a pas été généré: {output_file}")

            # Lire le WAV généré
            with wave.open(output_file, 'rb') as wav_file:
                frames = wav_file.readframes(wav_file.getnframes())
                audio_data = np.frombuffer(frames, dtype=np.int16).astype(np.float32) / 32768.0
                original_rate = wav_file.getframerate()

            os.remove(output_file)

            # Convertir en tensor PyTorch
            waveform = torch.from_numpy(audio_data).unsqueeze(0).unsqueeze(0)

            duration = len(audio_data) / original_rate
            print(f"✅ Audio généré: {duration:.1f}s")

            return ({"waveform": waveform, "sample_rate": original_rate},)

        except Exception as e:
            if os.path.exists(output_file):
                os.remove(output_file)
            raise Exception(f"Erreur génération Piper: {e}")


class AudioStreamOutput:
    """Sortie audio en streaming vers RTSP"""

    @classmethod
    def INPUT_TYPES(cls):
        return {
            "required": {
                "audio": ("AUDIO",),
                "format": ("STRING", {"default": "pcm16"}),
                "protocol": ("STRING", {"default": "rtsp"}),
                "url": ("STRING", {"default": "rtsp://0.0.0.0:8554/tts"}),
            }
        }

    RETURN_TYPES = ()
    FUNCTION = "stream"
    CATEGORY = "audio/output"
    OUTPUT_NODE = True

    def stream(self, audio, format, protocol, url):
        waveform = audio["waveform"]
        sample_rate = audio["sample_rate"]

        # Convertir en numpy
        audio_np = waveform.squeeze().cpu().numpy()

        # Sauvegarder temporairement en WAV
        with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as tmp:
            tmp_path = tmp.name

        # Écrire le fichier WAV avec la bibliothèque wave
        with wave.open(tmp_path, 'wb') as wav_file:
            wav_file.setnchannels(1)  # Mono
            wav_file.setsampwidth(2)  # 16-bit
            wav_file.setframerate(sample_rate)
            # Convertir float32 [-1, 1] en int16
            audio_int16 = (audio_np * 32767).astype(np.int16)
            wav_file.writeframes(audio_int16.tobytes())

        print(f"📡 Streaming vers {url}")

        # Streamer vers RTSP avec ffmpeg
        try:
            subprocess.Popen([
                "ffmpeg", "-re", "-i", tmp_path,
                "-c:a", "pcm_s16le",
                "-f", "rtsp", url
            ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

            print(f"✅ Stream démarré sur {url}")
        except Exception as e:
            print(f"❌ Erreur streaming: {e}")
        finally:
            # Nettoyer après un délai
            def cleanup():
                import time
                time.sleep(60)  # Attendre 60s avant de nettoyer
                try:
                    os.remove(tmp_path)
                except:
                    pass
            threading.Thread(target=cleanup, daemon=True).start()

        return {"ui": {"text": [f"Streaming to {url}"]}}


# Export pour ComfyUI
NODE_CLASS_MAPPINGS = {
    "PiperTTS": PiperTTS,
    "AudioStreamOutput": AudioStreamOutput,
}

NODE_DISPLAY_NAME_MAPPINGS = {
    "PiperTTS": "🎤 Piper TTS (Streaming)",
    "AudioStreamOutput": "📡 Audio Stream Output",
}
