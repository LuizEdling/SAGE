import { Component, OnInit, inject, signal } from '@angular/core';
import { JsonPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ChatService, ExecucaoAnterior, HistoricoTurno, SimulacaoUsuario } from './chat.service';
import {
  ExecutionKeyInUse,
  Message,
  SimulacaoExecucao,
} from '../../core/models/conversation.model';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { EmptyStateComponent } from '../../shared/components/empty-state/empty-state.component';
import { EvidenceTrailComponent } from '../../shared/components/evidence-trail/evidence-trail.component';
import { AuthService } from '../../core/services/auth.service';
import { ChatTextPipe } from '../../shared/pipes/chat-text.pipe';

type ModoSimulacao = 'padrao' | 'outro' | 'nenhum';

@Component({
  selector: 'app-chat',
  imports: [
    FormsModule,
    JsonPipe,
    PageHeaderComponent,
    EmptyStateComponent,
    EvidenceTrailComponent,
    ChatTextPipe,
  ],
  templateUrl: './chat.component.html',
  styleUrl: './chat.component.scss',
})
export class ChatComponent implements OnInit {
  private readonly chatService = inject(ChatService);
  private nextId = -1;

  protected readonly messages = signal<Message[]>([]);
  protected readonly draft = signal('');
  protected readonly sending = signal(false);
  protected readonly authService = inject(AuthService);
  /** Como simular: usuário de teste padrão, outro usuário (login e senha) ou sem usuário (só conhecimento). */
  protected readonly modo = signal<ModoSimulacao>('nenhum');
  protected readonly usuarioLogin = signal('');
  /** Só na memória da tela: nunca vai para o armazenamento do navegador. */
  protected readonly usuarioSenha = signal('');
  /** Chave de execução em uso; nula enquanto carrega (ou se o carregamento falhar). */
  protected readonly keyInUse = signal<ExecutionKeyInUse | null>(null);

  ngOnInit(): void {
    if (this.authService.isMaster()) {
      this.chatService.executionKeyInUse().subscribe({
        next: (key) => {
          this.keyInUse.set(key);

          // O usuário de teste padrão, quando configurado, já vem selecionado.
          if (key.simulation_login) {
            this.modo.set('padrao');
          }
        },
      });
    }
  }

  protected mudarModo(modo: ModoSimulacao): void {
    this.modo.set(modo);
    this.usuarioLogin.set('');
    this.usuarioSenha.set('');
  }

  /** "Outro usuário" só envia com login e senha preenchidos. */
  protected credenciaisCompletas(): boolean {
    return this.modo() !== 'outro' || (!!this.usuarioLogin().trim() && !!this.usuarioSenha());
  }

  private usuarioDaSimulacao(): SimulacaoUsuario | undefined {
    switch (this.modo()) {
      case 'padrao':
        return { padrao: true };
      case 'outro':
        return { login: this.usuarioLogin().trim(), senha: this.usuarioSenha() };
      default:
        return undefined;
    }
  }

  /** As consultas bem-sucedidas desta simulação, para o Agente saber o que já foi conferido. */
  private execucoesAnteriores(): ExecucaoAnterior[] {
    return this.messages()
      .flatMap((m) => m.metadata?.execucoes ?? [])
      .filter((e) => e.status === 'executada' && e.tipo === 'consulta')
      .slice(-10)
      .map((e) => ({
        function_id: e.function_id,
        tipo: e.tipo,
        status: 'executada' as const,
        parametros: e.parametros ?? {},
        dados: (Array.isArray(e.resultado) ? {} : (e.resultado.dados ?? {})) as Record<
          string,
          unknown
        >,
      }));
  }

  protected send(): void {
    const content = this.draft().trim();

    if (!content || this.sending() || !this.credenciaisCompletas()) {
      return;
    }

    this.sending.set(true);
    this.draft.set('');

    // Capturado antes de acrescentar a mensagem atual: o histórico é o que
    // veio *antes* dela.
    const historico = this.historicoAtual();
    const respondendoEsclarecimento = this.aguardandoEsclarecimento();
    const execucoesAnteriores = this.execucoesAnteriores();

    this.messages.update((msgs) => [
      ...msgs,
      {
        id: this.nextId--,
        content,
        author: 'usuario',
        knowledge_ids: [],
        metadata: null,
        executions: [],
        created_at: new Date().toISOString(),
      },
    ]);

    this.chatService
      .simulate(
        content,
        historico,
        respondendoEsclarecimento,
        this.usuarioDaSimulacao(),
        execucoesAnteriores,
      )
      .subscribe({
        next: (res) => {
          this.messages.update((msgs) => [
            ...msgs,
            {
              id: this.nextId--,
              content: res.resposta_texto,
              author: 'agente',
              knowledge_ids: [],
              metadata: res.diagnostico,
              executions: [],
              created_at: new Date().toISOString(),
            },
          ]);
          this.sending.set(false);
        },
        error: () => this.sending.set(false),
      });
  }

  protected temDetalhesDeFuncao(message: Message): boolean {
    const metadata = message.metadata;

    return (
      (metadata?.execucoes?.length ?? 0) > 0 ||
      !!metadata?.proposta ||
      !!metadata?.parametro_solicitado
    );
  }

  protected falhas(message: Message): number {
    return (message.metadata?.execucoes ?? []).filter((e) => e.status === 'falhou').length;
  }

  /** Uma linha para o cabeçalho do bloco recolhido. */
  protected resumoDeFuncoes(message: Message): string {
    const consultas = message.metadata?.execucoes?.length ?? 0;
    const partes: string[] = [];

    if (consultas > 0) {
      partes.push(`${consultas} ${consultas === 1 ? 'consulta' : 'consultas'} ao sistema`);
    }

    if (message.metadata?.proposta) {
      partes.push('1 alteração proposta');
    }

    if (message.metadata?.parametro_solicitado) {
      partes.push('pediu um dado ao usuário');
    }

    return `Detalhes técnicos · ${partes.join(' · ')}`;
  }

  /** Consulta feita, mas o usuário simulado não pode alterar a configuração: por isso não há proposta. */
  protected semPermissaoParaAlterar(message: Message, execucao: SimulacaoExecucao): boolean {
    const resultado = execucao.resultado as { pode_alterar?: boolean };

    return (
      execucao.status === 'executada' &&
      resultado.pode_alterar === false &&
      !message.metadata?.proposta
    );
  }

  /** Por que a consulta falhou, em linguagem de quem opera o Painel. */
  protected explicarFalha(execucao: SimulacaoExecucao): string {
    const mensagem = (execucao.resultado as { mensagem?: string }).mensagem;

    const dica: Record<string, string> = {
      INVALIDO:
        'Login ou senha não aceitos pelo sistema legado (ou o usuário está inativo). Confira as credenciais em "Simular como".',
      SEM_PERMISSAO: 'O usuário simulado não tem permissão para esta consulta.',
      NAO_ENCONTRADO: 'O item procurado não existe nas entidades desse usuário.',
      ERP_UNAVAILABLE: 'O sistema legado não respondeu.',
      NO_ACTIVE_EXECUTION_KEY:
        'Não há chave de execução ativa. Ative uma em Chaves de acesso para o Agente conferir configurações.',
    };

    return dica[execucao.erro_codigo ?? ''] ?? mensagem ?? 'A consulta não retornou dados.';
  }

  private historicoAtual(): HistoricoTurno[] {
    return this.messages()
      .slice(-8)
      .map(
        (m) =>
          ({
            autor: m.author === 'usuario' ? 'usuario' : 'agente',
            conteudo: m.content,
          }) as HistoricoTurno,
      );
  }

  /** A última fala do Agente foi uma pergunta esperando resposta? */
  private aguardandoEsclarecimento(): boolean {
    const ultimaDoAgente = this.messages()
      .filter((m) => m.author === 'agente')
      .at(-1);

    return ultimaDoAgente?.metadata?.aguardando_esclarecimento ?? false;
  }
}
