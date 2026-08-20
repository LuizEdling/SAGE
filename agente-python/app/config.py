from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    qdrant_url: str = "http://sage-qdrant:6333"
    qdrant_collection: str = "sage_knowledge"
    embedding_model: str = "sentence-transformers/all-MiniLM-L6-v2"
    embedding_dim: int = 384

    # Retrieval em dois estágios (ver app/rerank.py):
    # 1) recall — o Qdrant devolve muitos candidatos com limiar baixo;
    recall_threshold: float = 0.20
    recall_multiplier: int = 4
    # 2) precisão — depois do re-rank híbrido, só sobrevive quem passa deste
    # corte, no máximo `top_k` itens.
    score_threshold: float = 0.35
    top_k: int = 5
    chunk_max_chars: int = 1500

    # Ciclo de refinamento (ver app/graph/graph.py): quando o diagnóstico sai
    # com confiança abaixo deste corte, o grafo reformula a consulta e volta
    # à recuperação em vez de responder com material fraco.
    confianca_minima: float = 0.55
    # Teto de refinamentos por pergunta. É o que garante a terminação do
    # ciclo — sem ele o grafo poderia reformular indefinidamente. Cada
    # refinamento custa uma chamada de LLM e uma busca a mais, então o padrão
    # é conservador.
    max_refinamentos: int = 1

    # Nome do sistema legado atendido (ex.: "GLPI"). É como o Agente se refere
    # ao sistema nas respostas — para o usuário final o sistema é o legado,
    # não o S.A.G.E. Vazio = "o sistema", sem nome.
    sistema_legado_nome: str = ""

    llm_provider: str = "template"  # template | ollama | openai | anthropic | gemini
    llm_api_key: str = ""
    llm_model: str = ""
    ollama_url: str = "http://sage-ollama:11434"
    # Rate limit do tier gratuito do Gemini: sem retry, um 429 pontual joga a
    # resposta pro template determinístico no meio de uma conversa.
    llm_max_retries: int = 2
    llm_retry_backoff: float = 2.0
    # Classificar a pergunta num módulo é tarefa simples e não precisa do
    # mesmo modelo que redige a resposta. Usar um modelo próprio aqui também
    # dobra a cota efetiva no tier gratuito, já que o limite diário do Gemini
    # é contado por modelo.
    llm_model_fast: str = "gemini-3.5-flash-lite"
    # O avaliador decide se o material recuperado sustenta a resposta, e é a
    # principal defesa contra alucinação. Medido na base do GLPI: o modelo leve
    # lia a documentação de forma literal demais e recusava material correto
    # ("mais de uma pessoa em cada campo" não respondia a "mais de um
    # requerente"). Vazio = usa o modelo principal (llm_model).
    llm_model_avaliador: str = ""
    # Cota do free tier é contada por modelo. Quando o principal esgota (429)
    # ou está sobrecarregado (503), tentar outro modelo é melhor do que
    # devolver o texto cru do fallback determinístico ao usuário.
    llm_model_fallbacks: str = "gemini-3.5-flash-lite,gemini-3.1-flash-lite"
    # Orçamento de "pensamento" do Gemini. Medido em produção: sem limite o
    # modelo gastava ~1.300 tokens de raciocínio para entregar ~60 tokens de
    # resposta, e isso — não a rede — respondia por 90% da latência do
    # /diagnose. As duas tarefas aqui não exigem raciocínio longo: classificar
    # a pergunta num módulo é escolher entre poucas opções, e redigir a
    # resposta é reescrever um contexto que o RAG já selecionou. -1 remove o
    # limite (comportamento antigo), 0 desliga o pensamento.
    llm_thinking_budget: int = 128

    class Config:
        env_prefix = ""


settings = Settings()
