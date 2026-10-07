import { Component, OnInit, inject, signal } from '@angular/core';
import { DatePipe, JsonPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ExecutionService } from '../execution.service';
import { Execution } from '../../../core/models/execution.model';
import { Paginated } from '../../../core/models/api-response.model';
import {
  EXECUTION_STATUS_LABEL,
  ExecutionStatus,
  OPERATION_TYPE_LABEL,
  OperationType,
} from '../../../core/models/enums';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { PaginationComponent } from '../../../shared/components/pagination/pagination.component';

/**
 * Auditoria das funções do sistema legado usadas pelo Agente: o que foi lido
 * ou alterado, a pedido de quem, com quais parâmetros e com qual resultado.
 */
@Component({
  selector: 'app-execution-list',
  imports: [
    DatePipe,
    JsonPipe,
    RouterLink,
    EmptyStateComponent,
    PageHeaderComponent,
    PaginationComponent,
  ],
  templateUrl: './execution-list.component.html',
})
export class ExecutionListComponent implements OnInit {
  private readonly service = inject(ExecutionService);

  protected readonly items = signal<Execution[]>([]);
  protected readonly loading = signal(true);
  protected readonly page = signal(1);
  protected readonly meta = signal<Paginated<unknown>['meta'] | null>(null);
  protected readonly statusFilter = signal<ExecutionStatus | undefined>(undefined);
  protected readonly typeFilter = signal<OperationType | undefined>(undefined);

  ngOnInit(): void {
    this.load();
  }

  protected setStatusFilter(status: ExecutionStatus | undefined): void {
    this.statusFilter.set(status);
    this.page.set(1);
    this.load();
  }

  protected setTypeFilter(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.typeFilter.set((value || undefined) as OperationType | undefined);
    this.page.set(1);
    this.load();
  }

  protected goToPage(page: number): void {
    this.page.set(page);
    this.load();
  }

  protected statusLabel(status: ExecutionStatus): string {
    return EXECUTION_STATUS_LABEL[status];
  }

  protected typeLabel(type: OperationType | null): string {
    return type ? OPERATION_TYPE_LABEL[type] : '—';
  }

  private load(): void {
    this.loading.set(true);
    this.service.list(this.statusFilter(), this.typeFilter(), this.page()).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }
}
