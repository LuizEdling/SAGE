import { Component, OnInit, inject, signal } from '@angular/core';
import { AccessKeyService } from '../access-key.service';
import { ChatIntegration } from '../../../core/models/access-key.model';

const EXEMPLO_MENSAGEM = `{
  "content": "Não estou recebendo e-mail quando um pedido é aprovado",
  "usuario_erp": {
    "id": 7,
    "login": "maria.silva",
    "nome": "Maria Silva",
    "perfil_id": 3,
    "perfil": "Comprador",
    "interface": "portal",
    "entidade_id": 1,
    "entidade": "Filial Curitiba",
    "recursivo": false
  },
  "pagina": "/compras/pedidos/1542",
  "pagina_titulo": "Pedido de compra 1542"
}`;

const EXEMPLO_RESPOSTA = `HTTP 201
{
  "data": {
    "conversation": {
      "id": 31,
      "status": "aberta",
      "messages": [
        { "id": 120, "author": "usuario", "content": "Não estou recebendo e-mail…", "executions": [] },
        { "id": 121, "author": "agente", "content": "A notificação do evento…", "executions": [
          { "id": 58, "status": "pendente", "function": { "code": "notificacao.evento.alterar", "name": "…", "operation_type": "alteracao" },
            "parameters": { "evento": "pedido_aprovado" }, "expires_at": "2026-11-25T14:10:00-03:00" }
        ] }
      ]
    },
    "message_id": 120,
    "agent_message_id": 121
  }
}`;

const EXEMPLO_AUTORIZAR = `POST {api}/executions/58/authorize
Authorization: Bearer <chave de chat>

{
  "usuario_erp": { "id": 7, "login": "maria.silva", "perfil_id": 3, "entidade_id": 1, "recursivo": false },
  "token_autorizacao": "<token emitido pelo sistema legado no clique em Autorizar>"
}`;

/**
 * Explica, na tela Chaves de acesso, para onde e como o servidor do sistema
 * legado manda o chat. É a contraparte do guia "Como criar funções".
 */
@Component({
  selector: 'app-chat-integration-guide',
  templateUrl: './chat-integration-guide.component.html',
  styleUrl: './chat-integration-guide.component.scss',
})
export class ChatIntegrationGuideComponent implements OnInit {
  private readonly service = inject(AccessKeyService);

  protected readonly integration = signal<ChatIntegration | null>(null);
  protected readonly copied = signal(false);

  protected readonly exemploMensagem = EXEMPLO_MENSAGEM;
  protected readonly exemploResposta = EXEMPLO_RESPOSTA;

  ngOnInit(): void {
    this.service.chatIntegration().subscribe((data) => this.integration.set(data));
  }

  protected exemploAutorizar(): string {
    return EXEMPLO_AUTORIZAR.replace('{api}', this.integration()?.api_url ?? '{URL da API}');
  }

  protected copyUrl(): void {
    const url = this.integration()?.api_url;
    if (!url) return;
    navigator.clipboard.writeText(url).then(() => {
      this.copied.set(true);
      setTimeout(() => this.copied.set(false), 2000);
    });
  }
}
