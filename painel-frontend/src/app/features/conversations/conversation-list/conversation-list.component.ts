import { Component, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ConversationService } from '../conversation.service';
import { Conversation } from '../../../core/models/conversation.model';
import { ConversationStatus, CONVERSATION_STATUS_LABEL } from '../../../core/models/enums';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

import { PaginationComponent } from '../../../shared/components/pagination/pagination.component';
import { Paginated } from '../../../core/models/api-response.model';
@Component({
  selector: 'app-conversation-list',
  imports: [PaginationComponent, DatePipe, RouterLink, EmptyStateComponent, PageHeaderComponent],
  templateUrl: './conversation-list.component.html',
})
export class ConversationListComponent implements OnInit {
  private readonly service = inject(ConversationService);

  protected readonly items = signal<Conversation[]>([]);
  protected readonly loading = signal(true);
  protected readonly page = signal(1);
  protected readonly meta = signal<Paginated<unknown>['meta'] | null>(null);

  ngOnInit(): void {
    this.load();
  }

  protected goToPage(page: number): void {
    this.page.set(page);
    this.load();
  }

  private load(): void {
    this.loading.set(true);
    this.service.list(this.page()).subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.meta.set(res.meta);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  protected statusLabel(status: ConversationStatus): string {
    return CONVERSATION_STATUS_LABEL[status];
  }
}
