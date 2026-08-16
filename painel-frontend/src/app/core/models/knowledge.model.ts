import { ApprovalStatus, KnowledgeType } from './enums';
import { Component } from './component.model';
import { User } from './user.model';

export interface KnowledgeImage {
  id: number;
  url: string;
  caption: string | null;
}

export interface Knowledge {
  id: number;
  type: KnowledgeType;
  content: string;
  status: ApprovalStatus;
  valid_until: string | null;
  qdrant_point_id: string | null;
  component: Component | null;
  approved_by: User | null;
  approved_at: string | null;
  images: KnowledgeImage[];
  /** Funções indicadas para o problema deste conhecimento (vão nos metadados do Qdrant). */
  function_ids: number[];
  created_at: string;
}

export interface KnowledgePayload {
  type: KnowledgeType;
  content: string;
  component_id: number;
  valid_until?: string | null;
}

export interface DuplicateCandidate {
  knowledge_id: number;
  content: string;
  score: number;
}

export interface KnowledgeImportItem {
  file: string;
  status: 'created' | 'error';
  knowledge_id: number | null;
  modulo: string | null;
  message: string | null;
  duplicates: number;
}

export interface KnowledgeImportResult {
  total: number;
  created: number;
  failed: number;
  items: KnowledgeImportItem[];
}
