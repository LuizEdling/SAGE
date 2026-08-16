import { ExecutionStatus, OperationType } from './enums';

/** Função do sistema legado executada (consulta) ou proposta (alteração) pelo Agente. */
export interface Execution {
  id: number;
  status: ExecutionStatus;
  function: {
    id: number;
    code: string | null;
    name: string | null;
    operation_type: OperationType | null;
  };
  parameters: Record<string, unknown>;
  /** Dados lidos, ou antes/depois da alteração, como o sistema legado devolveu. */
  result: Record<string, unknown> | null;
  error_code: string | null;
  /** false = consulta autorizada por política (sem clique do usuário). */
  confirmed_by_user: boolean;
  expires_at: string | null;
  message_id: number;
  conversation: { id: number; erp_user_login: string | null; erp_user_name: string | null } | null;
  created_at: string;
  authorized_at: string | null;
  executed_at: string | null;
}
