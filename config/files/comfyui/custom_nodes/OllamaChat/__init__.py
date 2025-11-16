import ollama

class OllamaChat:
    @classmethod
    def INPUT_TYPES(cls):
        return {
            "required": {
                "modele": (["gemma2:2b", "phi3:mini", "mistral:7b"], {"default": "gemma2:2b"}),
                "instructions_systeme": ("STRING", {"multiline": True, "default": "Tu es un assistant virtuel serviable."}),
                "message_utilisateur": ("STRING", {"multiline": True, "default": "Bonjour!"}),
                "creativite": ("FLOAT", {"default": 0.7, "min": 0.0, "max": 2.0, "step": 0.1})
            }
        }

    RETURN_TYPES = ("STRING",)
    RETURN_NAMES = ("reponse",)
    FUNCTION = "chat"
    CATEGORY = "LLM"

    def chat(self, modele, instructions_systeme, message_utilisateur, creativite):
        try:
            try:
                ollama.show(modele)
            except:
                print(f"Telechargement du modele {modele}...")
                ollama.pull(modele)

            response = ollama.chat(
                model=modele,
                messages=[
                    {"role": "system", "content": instructions_systeme},
                    {"role": "user", "content": message_utilisateur}
                ],
                options={"temperature": creativite}
            )
            return (response["message"]["content"],)
        except Exception as e:
            return (f"Erreur: {str(e)}",)

NODE_CLASS_MAPPINGS = {"OllamaChat": OllamaChat}
NODE_DISPLAY_NAME_MAPPINGS = {"OllamaChat": "Chatbot Ollama"}
