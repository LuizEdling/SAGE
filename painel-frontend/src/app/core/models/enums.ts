export type ApprovalStatus = 'pendente' | 'aprovado' | 'reprovado';
export type KnowledgeType = 'base' | 'temporario';
export type OperationType = 'consulta' | 'alteracao';
export type ParameterType = 'string' | 'integer' | 'boolean' | 'date' | 'float';
export type ConversationStatus = 'aberta' | 'encerrada';
export type MessageAuthor = 'usuario' | 'agente' | 'sistema';
export type ExecutionStatus = 'pendente' | 'autorizada' | 'executada' | 'negada' | 'falhou';

export const APPROVAL_STATUS_LABEL: Record<ApprovalStatus, string> = {
  pendente: 'Pendente',
  aprovado: 'Aprovado',
  reprovado: 'Reprovado',
};

export const CONVERSATION_STATUS_LABEL: Record<ConversationStatus, string> = {
  aberta: 'Aberta',
  encerrada: 'Encerrada',
};

export const EXECUTION_STATUS_LABEL: Record<ExecutionStatus, string> = {
  pendente: 'Aguardando o usuário',
  autorizada: 'Autorizada',
  executada: 'Executada',
  negada: 'Negada',
  falhou: 'Falhou',
};

export const OPERATION_TYPE_LABEL: Record<OperationType, string> = {
  consulta: 'Consulta',
  alteracao: 'Alteração',
};
