import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import {
  AgentFunction,
  FunctionRecommendationResult,
  ManifestImportResult,
} from '../../core/models/agent-function.model';
import { Paginated } from '../../core/models/api-response.model';

@Injectable({ providedIn: 'root' })
export class FunctionService {
  private readonly api = inject(ApiService);

  list(status?: string, componentId?: number, page?: number): Observable<Paginated<AgentFunction>> {
    return this.api.get<Paginated<AgentFunction>>('/functions', {
      status,
      component_id: componentId,
      page,
    });
  }

  find(id: number): Observable<AgentFunction> {
    return this.api.get<AgentFunction>(`/functions/${id}`);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`/functions/${id}`);
  }

  approve(id: number): Observable<AgentFunction> {
    return this.api.post<AgentFunction>(`/functions/${id}/approve`);
  }

  reject(id: number, reason?: string): Observable<AgentFunction> {
    return this.api.post<AgentFunction>(`/functions/${id}/reject`, { reason });
  }

  toggleActive(id: number): Observable<AgentFunction> {
    return this.api.patch<AgentFunction>(`/functions/${id}/active`);
  }

  /** "Recomendar função": o Agente sugere funções do módulo para o problema do conhecimento. */
  recommend(knowledgeId: number): Observable<FunctionRecommendationResult> {
    return this.api.post<FunctionRecommendationResult>('/functions/recommend', {
      knowledge_id: knowledgeId,
    });
  }

  /** Cadastra as funções declaradas pelo plugin do sistema legado (todas entram pendentes). */
  importManifest(): Observable<ManifestImportResult> {
    return this.api.post<ManifestImportResult>('/functions/import-manifest');
  }
}
