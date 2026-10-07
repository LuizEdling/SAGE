import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import { Execution } from '../../core/models/execution.model';
import { Paginated } from '../../core/models/api-response.model';

@Injectable({ providedIn: 'root' })
export class ExecutionService {
  private readonly api = inject(ApiService);

  list(status?: string, operationType?: string, page?: number): Observable<Paginated<Execution>> {
    return this.api.get<Paginated<Execution>>('/executions', {
      status,
      operation_type: operationType,
      page,
    });
  }
}
