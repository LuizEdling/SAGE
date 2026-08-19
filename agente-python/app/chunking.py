def chunk_text(content: str, max_chars: int = 1500) -> list[str]:
    """Divide por parágrafo (nunca no meio de uma frase). Se o conteúdo já
    couber inteiro, retorna um único chunk — é o caso mais comum, já que cada
    `knowledge` cadastrado no Painel normalmente já é uma unidade coesa."""
    content = content.strip()

    if len(content) <= max_chars:
        return [content] if content else []

    paragraphs = [p.strip() for p in content.split("\n\n") if p.strip()]

    if not paragraphs:
        paragraphs = [content]

    chunks: list[str] = []
    current = ""

    for paragraph in paragraphs:
        candidate = f"{current}\n\n{paragraph}".strip() if current else paragraph

        if len(candidate) > max_chars and current:
            chunks.append(current)
            current = paragraph
        else:
            current = candidate

    if current:
        chunks.append(current)

    return chunks
