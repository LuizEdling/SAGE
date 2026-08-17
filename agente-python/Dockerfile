FROM python:3.12-slim

WORKDIR /app

# libgomp1 é exigido pelo torch (dependência do sentence-transformers) em runtime.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libgomp1 curl \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir --extra-index-url https://download.pytorch.org/whl/cpu -r requirements.txt

COPY . .

# Baixa o modelo de embeddings no build, para não depender de rede no primeiro
# request em runtime (senão a primeira chamada de /ingest ficaria lenta/instável).
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('sentence-transformers/all-MiniLM-L6-v2')"

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
