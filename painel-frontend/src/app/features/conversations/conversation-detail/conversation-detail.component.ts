import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { DatePipe, DecimalPipe } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ConversationService } from '../conversation.service';
import { ContextoErp, Conversation } from '../../../core/models/conversation.model';
import { ConversationStatus, CONVERSATION_STATUS_LABEL } from '../../../core/models/enums';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { EvidenceTrailComponent } from '../../../shared/components/evidence-trail/evidence-trail.component';
import { ExecutionCardComponent } from '../../../shared/components/execution-card/execution-card.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { ChatTextPipe } from '../../../shared/pipes/chat-text.pipe';

/**
 * Supervisão de uma conversa do chat embutido no sistema legado (§4.4):
 * mensagens, conhecimento usado em cada resposta e o diagnóstico interno do
 * Agente (módulo, confiança, iterações do refinamento, veredito do avaliador),
 * que o usuário final não vê.
 */
@Component({
  selector: 'app-conversation-detail',
  imports: [
    DatePipe,
    DecimalPipe,
    RouterLink,
    EmptyStateComponent,
    EvidenceTrailComponent,
    ExecutionCardComponent,
    PageHeaderComponent,
    ChatTextPipe,
  ],
  templateUrl: './conversation-detail.component.html',
  styleUrl: './conversation-detail.component.scss',
})
export class ConversationDetailComponent implements OnInit {
  private readonly service = inject(ConversationService);
  private readonly route = inject(ActivatedRoute);

  protected readonly conversation = signal<Conversation | null>(null);
  protected readonly loading = signal(true);
  protected readonly notFound = signal(false);

  /** Perfil e entidade do usuário na primeira pergunta da conversa. */
  protected readonly contexto = computed<ContextoErp | null>(
    () =>
      this.conversation()?.messages.find((m) => m.metadata?.contexto_erp)?.metadata?.contexto_erp ??
      null,
  );

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    this.service.find(id).subscribe({
      next: (conversation) => {
        this.conversation.set(conversation);
        this.loading.set(false);
      },
      error: () => {
        this.notFound.set(true);
        this.loading.set(false);
      },
    });
  }

  protected statusLabel(status: ConversationStatus): string {
    return CONVERSATION_STATUS_LABEL[status];
  }
}
