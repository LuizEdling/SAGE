# S.A.G.E. — Sistema de Agentes para Gestão do Conhecimento Especializado

Plataforma de gestão do conhecimento (Painel) e Agente de IA multiagente com RAG
para diagnosticar problemas reportados por usuários de sistemas legados.

| Pasta | Conteúdo |
|---|---|
| `painel-backend/` | API do Painel — Laravel 13 (PHP 8.4), MySQL, Sanctum |
| `painel-frontend/` | Interface do Painel — Angular 20 |
| `agente-python/` | Agente — FastAPI + LangGraph, RAG com Qdrant |
| `docker/` | Configuração dos serviços de apoio (MySQL) |

## Como executar

Requer apenas Docker.

```bash
cp .env.example .env
cp painel-backend/.env.example painel-backend/.env
make up      # sobe toda a stack (docker compose up -d)
make fresh   # cria o banco e popula os dados iniciais
```

- Painel: http://localhost:4200
- API: http://localhost:8000
- Agente: http://localhost:8001

Para usar um LLM na resposta do Agente, defina `LLM_PROVIDER`, `LLM_API_KEY` e
`LLM_MODEL` no `.env` (o padrão `template` funciona sem chave).
