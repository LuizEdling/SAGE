import { ApprovalStatus, OperationType } from './enums';
import { Component } from './component.model';
import { User } from './user.model';
import { FunctionParameter } from './function-parameter.model';

export interface AgentFunction {
  id: number;
  /** Identificador da função no sistema legado (o que o Painel envia na chamada de execução). */
  code: string | null;
  name: string;
  description: string;
  operation_type: OperationType;
  status: ApprovalStatus;
  active: boolean;
  component: Component | null;
  approved_by: User | null;
  approved_at: string | null;
  parameters: FunctionParameter[];
  created_at: string;
}

export interface ManifestImportItem {
  code: string;
  name: string;
  status: 'criada' | 'atualizada' | 'inalterada' | 'erro';
  message: string | null;
}

export interface ManifestImportResult {
  items: ManifestImportItem[];
  created: number;
  updated: number;
  failed: number;
}

export interface FunctionRecommendation {
  function: AgentFunction;
  reason: string | null;
}

export interface FunctionRecommendationResult {
  /** false = não havia modelo de linguagem disponível para recomendar. */
  available: boolean;
  recommendations: FunctionRecommendation[];
}
