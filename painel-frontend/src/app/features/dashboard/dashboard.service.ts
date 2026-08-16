import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';

export interface DashboardStats {
  pendentes: number;
  aprovados: number;
  indexados: number;
  modulos: number;
  conversas: number;
}

@Injectable({ providedIn: 'root' })
export class DashboardService {
  private readonly api = inject(ApiService);

  stats(): Observable<DashboardStats> {
    return this.api.get<DashboardStats>('/dashboard/stats');
  }
}
