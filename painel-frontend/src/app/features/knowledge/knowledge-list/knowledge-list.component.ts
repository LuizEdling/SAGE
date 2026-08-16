import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { KnowledgeService } from '../knowledge.service';
import { Knowledge } from '../../../core/models/knowledge.model';
import { AuthService } from '../../../core/services/auth.service';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconEditLinkComponent } from '../../../shared/components/icon-edit-link/icon-edit-link.component';
import { IconDeleteButtonComponent } from '../../../shared/components/icon-delete-button/icon-delete-button.component';
import { IconApproveButtonComponent } from '../../../shared/components/icon-approve-button/icon-approve-button.component';
import { IconRejectButtonComponent } from '../../../shared/components/icon-reject-button/icon-reject-button.component';
import { IconHelpComponent } from '../../../shared/components/icon-help/icon-help.component';
import { MarkdownPreviewPipe } from '../../../shared/pipes/markdown-preview.pipe';

import { PaginationComponent } from '../../../shared/components/pagination/pagination.component';
import { Paginated } from '../../../core/models/api-response.model';
@Component({
  selector: 'app-knowledge-list',
  imports: [
    PaginationComponent,
    RouterLink,
    EmptyStateComponent,
    StatusBadgeComponent,
    PageHeaderComponent,
    IconEditLinkComponent,
    IconDeleteButtonComponent,
    IconApproveButtonComponent,
    IconRejectButtonComponent,
    IconHelpComponent,
    MarkdownPreviewPipe,
  ],
  templateUrl: './knowledge-list.component.html',
})
export class KnowledgeListComponent implements OnInit {
  private readonly service = inject(KnowledgeService);
  private readonly route = inject(ActivatedRoute);

  protected readonly authService = inject(AuthService);
  protected readonly items = signal<Knowledge[]>([]);
  protected readonly loading = signal(true);
  protected readonly page = signal(1);
  protected readonly meta = signal<Paginated<unknown>['meta'] | null>(null);
  protected readonly statusFilter = signal<string | undefined>(undefined);

  ngOnInit(): void {
    this.route.queryParamMap.subscribe((params) => {
      this.statusFilter.set(params.get('status') ?? undefined);
      this.page.set(1);
      this.load();
    });
  }

  protected goToPage(page: number): void {
    this.page.set(page);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.service.list(this.statusFilter(), this.page()).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  protected approve(id: number): void {
    this.service.approve(id).subscribe(() => this.load());
  }

  protected reject(id: number): void {
    const reason = prompt('Motivo da reprovação (opcional):') ?? undefined;
    this.service.reject(id, reason).subscribe(() => this.load());
  }

  protected remove(id: number): void {
    if (!confirm('Remover este conhecimento?')) {
      return;
    }

    this.service.delete(id).subscribe(() => this.load());
  }
}
