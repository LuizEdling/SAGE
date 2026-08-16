import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import {
  ChatAccessKey,
  ChatIntegration,
  CreatedChatAccessKey,
  ErpConnectionTest,
  ErpExecutionKey,
} from '../../core/models/access-key.model';
import { Paginated } from '../../core/models/api-response.model';

@Injectable({ providedIn: 'root' })
export class AccessKeyService {
  private readonly api = inject(ApiService);

  listChatKeys(): Observable<Paginated<ChatAccessKey>> {
    return this.api.get<Paginated<ChatAccessKey>>('/chat-access-keys');
  }

  createChatKey(): Observable<CreatedChatAccessKey> {
    return this.api.post<CreatedChatAccessKey>('/chat-access-keys');
  }

  toggleChatKey(id: number, active: boolean): Observable<ChatAccessKey> {
    return this.api.put<ChatAccessKey>(`/chat-access-keys/${id}`, { active });
  }

  deleteChatKey(id: number): Observable<void> {
    return this.api.delete<void>(`/chat-access-keys/${id}`);
  }

  chatIntegration(): Observable<ChatIntegration> {
    return this.api.get<ChatIntegration>('/chat-integration');
  }

  listErpKeys(): Observable<Paginated<ErpExecutionKey>> {
    return this.api.get<Paginated<ErpExecutionKey>>('/erp-execution-keys');
  }

  createErpKey(description: string, apiUrl: string): Observable<ErpExecutionKey> {
    return this.api.post<ErpExecutionKey>('/erp-execution-keys', { description, api_url: apiUrl });
  }

  updateErpKeyUrl(id: number, apiUrl: string): Observable<ErpExecutionKey> {
    return this.api.put<ErpExecutionKey>(`/erp-execution-keys/${id}`, { api_url: apiUrl });
  }

  testErpConnection(id: number): Observable<ErpConnectionTest> {
    return this.api.post<ErpConnectionTest>(`/erp-execution-keys/${id}/test-connection`);
  }

  toggleErpKey(id: number, active: boolean): Observable<ErpExecutionKey> {
    return this.api.put<ErpExecutionKey>(`/erp-execution-keys/${id}`, { active });
  }

  deleteErpKey(id: number): Observable<void> {
    return this.api.delete<void>(`/erp-execution-keys/${id}`);
  }
}
