import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import { ExecutionKeyInUse, MessageMetadata } from '../../core/models/conversation.model';

export interface SimulateChatResponse {
  resposta_texto: string;
  diagnostico: MessageMetadata;
}

/** Como a simulação age no sistema legado: o usuário de teste padrão (credenciais no servidor) ou login e senha de outro usuário. */
export type SimulacaoUsuario = { padrao: true } | { login: string; senha: string };

/** Consulta já feita na conversa; viaja de volta para o Agente não repeti-la. */
export interface ExecucaoAnterior {
  function_id: number;
  tipo: string;
  status: 'executada';
  parametros: Record<string, unknown>;
  dados: Record<string, unknown>;
}

export interface HistoricoTurno {
  autor: 'usuario' | 'agente';
  conteudo: string;
}

@Injectable({ providedIn: 'root' })
export class ChatService {
  private readonly api = inject(ApiService);

  // A simulação não persiste conversa, então o histórico viaja do navegador —
  // é ele que permite o Agente pedir um dado e entender a resposta seguinte.
  // `usuario`: simula como um usuário do sistema legado — o Agente passa a
  // conferir configurações reais com os direitos dele (só o gestor), sempre
  // com credenciais conferidas pelo próprio sistema legado.
  simulate(
    content: string,
    historico: HistoricoTurno[] = [],
    respondendoEsclarecimento = false,
    usuario?: SimulacaoUsuario,
    execucoesAnteriores: ExecucaoAnterior[] = [],
  ): Observable<SimulateChatResponse> {
    return this.api.post<SimulateChatResponse>('/chat/simulate', {
      content,
      historico,
      respondendo_esclarecimento: respondendoEsclarecimento,
      execucoes_anteriores: execucoesAnteriores,
      ...(usuario ? { usuario_erp: usuario } : {}),
    });
  }

  /** Chave de execução em uso, para o gestor saber com qual credencial a simulação consulta o sistema legado. */
  executionKeyInUse(): Observable<ExecutionKeyInUse> {
    return this.api.get<ExecutionKeyInUse>('/erp/execution-key');
  }
}
