# Compila o Angular e entrega os arquivos estáticos pelo Caddy, que também
# faz o HTTPS automático e encaminha /api e /storage para a API do Painel.
# Contexto do build: raiz do repositório.

FROM node:22-alpine AS build
WORKDIR /app
COPY painel-frontend/package.json painel-frontend/package-lock.json ./
RUN npm ci
COPY painel-frontend/ .
RUN npx ng build --configuration production

FROM caddy:2-alpine
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist/painel-frontend/browser /srv
