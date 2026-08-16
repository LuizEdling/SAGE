import { User } from './user.model';

export interface ChatAccessKey {
  id: number;
  /** Conversas que chegaram por esta chave; chave usada não pode ser removida. */
  conversations_count: number | null;
  active: boolean;
  created_by: User | null;
  created_at: string;
}

// O valor em claro só chega na resposta de criação; depois disso o banco guarda
// apenas o hash e a chave não é mais recuperável.
export interface CreatedChatAccessKey extends ChatAccessKey {
  access_key: string;
}

export interface ErpExecutionKey {
  id: number;
  access_key: string;
  /** Endereço base da API do plugin no sistema legado (onde ficam /manifest e /functions). */
  api_url: string;
  description: string;
  active: boolean;
  created_at: string;
}

export interface ErpConnectionTest {
  ok: boolean;
  http_status: number | null;
  message: string;
  functions: number | null;
}

/** Como o sistema legado chega ao Painel para o chat (tela Chaves de acesso). */
export interface ChatIntegration {
  api_url: string;
  rate_limit_per_minute: number;
  execution_ttl_minutes: number;
}
