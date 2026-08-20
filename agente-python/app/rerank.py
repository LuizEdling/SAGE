"""Re-ranking híbrido dos candidatos devolvidos pelo Qdrant.

A busca vetorial sozinha erra em dois casos comuns nesse domínio: termos
exatos que o usuário digita igual à interface do sistema legado (códigos,
nomes de botão, mensagens de erro) têm representação semântica fraca, e
chunks de módulos errados às vezes ficam semanticamente próximos. Por isso o
retrieval acontece em dois estágios:

  1. Recall — o Qdrant devolve um conjunto amplo de candidatos, com limiar
     baixo (pega mais do que precisa).
  2. Precisão — aqui: cada candidato é re-pontuado combinando três sinais
     (denso, léxico/BM25 e aderência ao módulo classificado), e só então
     aplica-se o corte final e o top-k.

É uma fusão densa+esparsa clássica, sem cross-encoder de propósito: um
re-ranker neural exigiria baixar outro modelo (os de referência são treinados
em inglês, e os multilíngues são pesados) para ganho incerto num corpus deste
tamanho. Isso aqui é determinístico, roda em milissegundos e é explicável.
"""

import math
import re
import unicodedata

# Peso de cada sinal na pontuação final. Denso domina (é o que entende
# paráfrase), léxico corrige termo exato, módulo desempata.
PESO_DENSO = 0.60
PESO_LEXICO = 0.25
PESO_MODULO = 0.15

_BM25_K1 = 1.5
_BM25_B = 0.75

# Palavras que aparecem em quase toda pergunta de suporte — se entrassem no
# BM25 puxariam a pontuação de qualquer chunk, sem discriminar nada.
_STOPWORDS = {
    "a", "ao", "aos", "as", "com", "como", "da", "das", "de", "dele", "dela", "do", "dos",
    "e", "em", "essa", "esse", "esta", "este", "eu", "fazer", "faco", "faço", "for", "isso",
    "meu", "minha", "na", "nao", "não", "nas", "no", "nos", "o", "os", "ou", "para", "para",
    "pelo", "pela", "por", "pra", "pro", "qual", "quais", "quando", "que", "quem", "se",
    "sem", "ser", "seu", "sua", "tem", "ter", "um", "uma", "voce", "você", "sobre", "onde",
}


def normalizar(texto: str) -> str:
    sem_acento = unicodedata.normalize("NFKD", texto.lower())
    return "".join(c for c in sem_acento if not unicodedata.combining(c))


def _tokenizar(texto: str) -> list[str]:
    tokens = re.findall(r"[a-z0-9]+", normalizar(texto))
    return [t for t in tokens if len(t) >= 3 and t not in _STOPWORDS]


def _bm25(pergunta: str, documentos: list[str]) -> list[float]:
    """BM25 calculado sobre o próprio conjunto de candidatos. O IDF sai da
    distribuição local (não de um índice global) — para desempatar candidatos
    entre si isso basta, e evita manter uma estrutura de índice invertido."""
    docs_tokens = [_tokenizar(doc) for doc in documentos]
    n = len(docs_tokens)

    if n == 0:
        return []

    tamanhos = [len(d) for d in docs_tokens]
    tamanho_medio = sum(tamanhos) / n or 1.0
    termos_pergunta = set(_tokenizar(pergunta))

    if not termos_pergunta:
        return [0.0] * n

    scores = []
    for tokens, tamanho in zip(docs_tokens, tamanhos):
        score = 0.0
        for termo in termos_pergunta:
            tf = tokens.count(termo)

            if tf == 0:
                continue

            df = sum(1 for d in docs_tokens if termo in d)
            idf = math.log(1 + (n - df + 0.5) / (df + 0.5))
            denominador = tf + _BM25_K1 * (1 - _BM25_B + _BM25_B * tamanho / tamanho_medio)
            score += idf * (tf * (_BM25_K1 + 1)) / denominador

        scores.append(score)

    return scores


def _min_max(valores: list[float]) -> list[float]:
    if not valores:
        return []

    menor, maior = min(valores), max(valores)

    if maior - menor < 1e-9:
        # Todos iguais: sinal não discrimina nada, então é neutro (não pode
        # virar 1.0 pra todo mundo, senão infla a pontuação final à toa).
        return [0.0 for _ in valores]

    return [(v - menor) / (maior - menor) for v in valores]


def rerank(
    pergunta: str,
    candidatos: list[dict],
    modulo_id: int | None = None,
    limite: int = 5,
    corte: float = 0.35,
) -> list[dict]:
    """Re-pontua e ordena os candidatos, devolvendo no máximo `limite` itens
    acima de `corte`. Cada candidato ganha `score` (final) e `score_denso`
    (o original do Qdrant, preservado para auditoria/trilha de evidência)."""
    if not candidatos:
        return []

    densos = [c["score"] for c in candidatos]
    lexicos = _bm25(pergunta, [c["content"] for c in candidatos])

    densos_norm = _min_max(densos)
    lexicos_norm = _min_max(lexicos)

    ranqueados = []
    for i, candidato in enumerate(candidatos):
        aderencia_modulo = 1.0 if modulo_id is not None and candidato.get("component_id") == modulo_id else 0.0

        # O denso normalizado sozinho perderia a escala absoluta (um conjunto
        # todo ruim ainda teria um "melhor" com 1.0), então ele entra
        # combinado com o score bruto: metade posição relativa, metade
        # qualidade absoluta.
        base_densa = 0.5 * densos_norm[i] + 0.5 * densos[i]

        score_final = (
            PESO_DENSO * base_densa
            + PESO_LEXICO * lexicos_norm[i]
            + PESO_MODULO * aderencia_modulo
        )

        ranqueados.append({**candidato, "score_denso": densos[i], "score": round(score_final, 4)})

    ranqueados.sort(key=lambda c: c["score"], reverse=True)

    return [c for c in ranqueados if c["score"] >= corte][:limite]
