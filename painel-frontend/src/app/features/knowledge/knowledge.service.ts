import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import {
  Knowledge,
  KnowledgePayload,
  DuplicateCandidate,
  KnowledgeImportResult,
} from '../../core/models/knowledge.model';
import { Paginated } from '../../core/models/api-response.model';

@Injectable({ providedIn: 'root' })
export class KnowledgeService {
  private readonly api = inject(ApiService);

  list(status?: string, page?: number): Observable<Paginated<Knowledge>> {
    return this.api.get<Paginated<Knowledge>>('/knowledges', { status, page });
  }

  find(id: number): Observable<Knowledge> {
    return this.api.get<Knowledge>(`/knowledges/${id}`);
  }

  create(payload: KnowledgePayload): Observable<Knowledge> {
    return this.api.post<Knowledge>('/knowledges', payload);
  }

  update(id: number, payload: KnowledgePayload): Observable<Knowledge> {
    return this.api.put<Knowledge>(`/knowledges/${id}`, payload);
  }

  /** Só o gestor: o vínculo decide o que o Agente pode executar a partir deste conhecimento. */
  updateFunctions(id: number, functionIds: number[]): Observable<Knowledge> {
    return this.api.put<Knowledge>(`/knowledges/${id}/functions`, { function_ids: functionIds });
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`/knowledges/${id}`);
  }

  checkDuplicate(componentId: number, content: string): Observable<{ duplicates: DuplicateCandidate[] }> {
    return this.api.post('/knowledges/check-duplicate', { component_id: componentId, content });
  }

  approve(id: number): Observable<Knowledge> {
    return this.api.post<Knowledge>(`/knowledges/${id}/approve`);
  }

  reject(id: number, reason?: string): Observable<Knowledge> {
    return this.api.post<Knowledge>(`/knowledges/${id}/reject`, { reason });
  }

  uploadImage(knowledgeId: number, file: File, caption?: string): Observable<unknown> {
    const formData = new FormData();
    formData.append('image', file);
    if (caption) {
      formData.append('caption', caption);
    }

    return this.api.postForm(`/knowledges/${knowledgeId}/images`, formData);
  }

  importZip(file: File): Observable<KnowledgeImportResult> {
    const formData = new FormData();
    formData.append('file', file);

    return this.api.postForm<KnowledgeImportResult>('/knowledges/import', formData);
  }
}
