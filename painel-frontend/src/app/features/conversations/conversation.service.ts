import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import { Conversation } from '../../core/models/conversation.model';
import { Paginated } from '../../core/models/api-response.model';

@Injectable({ providedIn: 'root' })
export class ConversationService {
  private readonly api = inject(ApiService);

  list(page?: number): Observable<Paginated<Conversation>> {
    return this.api.get<Paginated<Conversation>>('/conversations', { page });
  }

  find(id: number): Observable<Conversation> {
    return this.api.get<Conversation>(`/conversations/${id}`);
  }
}
