import { ConversationStatus, MessageAuthor } from './enums';
import { Execution } from './execution.model';

export interface MessageFonte {
  knowledge_id: number;
  modulo: string;
  trecho: string;
  score: number;
}

export interface ModuloRecurso {
  modulo: string;
  rota: string | null;
  imagens: string[];
}

export interface MessageMetadata {
  resumo: string | null;
  causa_provavel: string | null;
  modulo_afetado: string | null;
  confianca: number;
  fontes: MessageFonte[];
  modulo_recurso: ModuloRecurso | null;
  /** O Agente devolveu uma pergunta: a próxima mensagem responde a ela. */
  aguardando_esclarecimento?: boolean;
  /** Voltas do ciclo de refinamento e as consultas usadas em cada uma. */
  iteracoes?: number;
  consultas?: string[];
  /** Veredito do agente avaliador: o material recuperado sustenta a resposta? */
  sustentado?: boolean | null;
  /** Mensagens do usuário: perfil/entidade/página no sistema legado ao perguntar. */
  contexto_erp?: ContextoErp;
  /** Só na simulação: consultas executadas e alteração proposta (nunca executada). */
  execucoes?: SimulacaoExecucao[];
  proposta?: SimulacaoProposta | null;
  /** O Agente escolheu uma função, mas faltou um dado e ele perguntou ao usuário. */
  parametro_solicitado?: ParametroSolicitado | null;
}

export interface ParametroSolicitado {
  codigo: string | null;
  funcao: string | null;
  tipo: 'consulta' | 'alteracao';
  parametros: { nome: string; descricao: string | null; motivo: 'ausente' | 'invalido' }[];
}

export interface SimulacaoExecucao {
  function_id: number;
  codigo: string;
  tipo: string;
  status: 'executada' | 'falhou';
  parametros: Record<string, unknown>;
  resultado:
    { dados?: Record<string, unknown>; pode_alterar?: boolean; mensagem?: string } | unknown[];
  erro_codigo: string | null;
}

export interface SimulacaoProposta {
  codigo: string;
  funcao: string;
  parametros: Record<string, unknown>;
}

export interface ContextoErp {
  perfil_id?: number;
  perfil?: string;
  interface?: 'central' | 'helpdesk';
  entidade_id?: number;
  entidade?: string;
  recursivo?: boolean;
  pagina?: string;
}

/** Usuário do sistema legado dono da conversa. */
export interface ErpUser {
  id: number;
  login: string | null;
  name: string | null;
}

export interface Message {
  id: number;
  content: string;
  author: MessageAuthor;
  knowledge_ids: number[];
  metadata: MessageMetadata | null;
  /** Funções executadas ou propostas a partir desta mensagem. */
  executions: Execution[];
  created_at: string;
}

export interface Conversation {
  id: number;
  status: ConversationStatus;
  erp_user: ErpUser | null;
  opened_at: string | null;
  closed_at: string | null;
  last_message_at: string | null;
  message_count: number | null;
  messages: Message[];
}

/** Chave de execução que o Painel usa nas chamadas ao sistema legado. */
export interface ExecutionKeyInUse {
  execution_key: { id: number; description: string } | null;
  /** Login do usuário de teste padrão da simulação (a senha nunca vem ao navegador). */
  simulation_login: string | null;
}
