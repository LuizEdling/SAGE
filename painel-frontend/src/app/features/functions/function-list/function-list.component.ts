import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FunctionService } from '../function.service';
import { AgentFunction, ManifestImportResult } from '../../../core/models/agent-function.model';
import { AuthService } from '../../../core/services/auth.service';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconDeleteButtonComponent } from '../../../shared/components/icon-delete-button/icon-delete-button.component';
import { IconApproveButtonComponent } from '../../../shared/components/icon-approve-button/icon-approve-button.component';
import { IconRejectButtonComponent } from '../../../shared/components/icon-reject-button/icon-reject-button.component';

import { PaginationComponent } from '../../../shared/components/pagination/pagination.component';
import { Paginated } from '../../../core/models/api-response.model';
import { OPERATION_TYPE_LABEL } from '../../../core/models/enums';
@Component({
  selector: 'app-function-list',
  imports: [
    PaginationComponent,
    RouterLink,
    EmptyStateComponent,
    StatusBadgeComponent,
    PageHeaderComponent,
    IconDeleteButtonComponent,
    IconApproveButtonComponent,
    IconRejectButtonComponent,
  ],
  templateUrl: './function-list.component.html',
})
export class FunctionListComponent implements OnInit {
  protected readonly operationTypeLabel = OPERATION_TYPE_LABEL;
  private readonly service = inject(FunctionService);

  protected readonly authService = inject(AuthService);
  protected readonly items = signal<AgentFunction[]>([]);
  protected readonly loading = signal(true);
  protected readonly page = signal(1);
  protected readonly meta = signal<Paginated<unknown>['meta'] | null>(null);
  protected readonly statusFilter = signal<string | undefined>(undefined);
  protected readonly importing = signal(false);
  protected readonly importResult = signal<ManifestImportResult | null>(null);
  protected readonly importError = signal<string | null>(null);

  ngOnInit(): void {
    this.load();
  }

  protected setStatusFilter(status: string | undefined): void {
    this.statusFilter.set(status);
    this.page.set(1);
    this.load();
  }

  protected goToPage(page: number): void {
    this.page.set(page);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.service.list(this.statusFilter(), undefined, this.page()).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  protected importManifest(): void {
    this.importing.set(true);
    this.importError.set(null);

    this.service.importManifest().subscribe({
      next: (result) => {
        this.importResult.set(result);
        this.importing.set(false);
        this.load();
      },
      error: (err) => {
        this.importError.set(
          err?.error?.error?.message ?? 'Não foi possível consultar as funções do sistema legado.',
        );
        this.importing.set(false);
      },
    });
  }

  protected approve(id: number): void {
    this.service.approve(id).subscribe(() => this.load());
  }

  protected reject(id: number): void {
    const reason = prompt('Motivo da reprovação (opcional):') ?? undefined;
    this.service.reject(id, reason).subscribe(() => this.load());
  }

  protected toggleActive(id: number): void {
    this.service.toggleActive(id).subscribe(() => this.load());
  }

  protected remove(id: number): void {
    if (!confirm('Remover esta função?')) {
      return;
    }

    this.service.delete(id).subscribe(() => this.load());
  }
}
