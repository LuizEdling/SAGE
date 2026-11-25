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

## Produção (um servidor com Docker)

Requer um servidor Linux com Docker (4 GB de RAM ou mais) e um domínio cujo
registro DNS `A` aponte para o IP do servidor, com as portas 80 e 443 abertas.

```bash
git clone https://github.com/LuizEdling/SAGE.git && cd SAGE
cp .env.production.example .env
make prod-key        # copie a linha APP_KEY=... para o .env
nano .env            # domínio, senhas do MySQL, gestor e LLM
make prod-up         # compila e sobe tudo; o Caddy emite o HTTPS
make prod-seed       # cria o primeiro gestor (SAGE_ADMIN_*)
```

Só o Caddy publica portas (80/443): ele serve o Angular e encaminha `/api` e
`/storage` para a API. MySQL, Qdrant e o Agente ficam na rede interna do Docker.
Para atualizar: `git pull && make prod-up`.
