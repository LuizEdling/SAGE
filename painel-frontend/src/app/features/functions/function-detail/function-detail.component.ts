import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FunctionService } from '../function.service';
import { AgentFunction } from '../../../core/models/agent-function.model';
import { OPERATION_TYPE_LABEL } from '../../../core/models/enums';
import { AuthService } from '../../../core/services/auth.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { StatusBadgeComponent } from '../../../shared/components/status-badge/status-badge.component';

/**
 * Detalhe de uma função importada do sistema legado. Só leitura: nome,
 * descrição e parâmetros nascem no sistema legado (é a descrição que o Agente
 * lê para decidir quando usar a função), então aqui o gestor revisa o que foi
 * importado antes de aprovar e ativar.
 */
@Component({
  selector: 'app-function-detail',
  imports: [RouterLink, PageHeaderComponent, StatusBadgeComponent],
  templateUrl: './function-detail.component.html',
  styles: [
    `
      .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem 2rem;
        margin: 0 0 1.25rem;
      }

      .detail-grid dt {
        margin-bottom: 0.2rem;
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--sage-text-muted);
      }

      .detail-grid dd {
        margin: 0;
      }

      .detail-description {
        grid-column: 1 / -1;
      }
    `,
  ],
})
export class FunctionDetailComponent implements OnInit {
  private readonly service = inject(FunctionService);
  private readonly route = inject(ActivatedRoute);

  protected readonly authService = inject(AuthService);
  protected readonly fn = signal<AgentFunction | null>(null);
  protected readonly loading = signal(true);
  protected readonly notFound = signal(false);

  protected readonly title = computed(() => this.fn()?.name ?? 'Função');
  protected readonly typeLabel = computed(() => {
    const type = this.fn()?.operation_type;

    return type ? OPERATION_TYPE_LABEL[type] : '';
  });

  ngOnInit(): void {
    this.load();
  }

  protected approve(): void {
    const id = this.fn()?.id;

    if (id) {
      this.service.approve(id).subscribe(() => this.load());
    }
  }

  protected reject(): void {
    const id = this.fn()?.id;

    if (!id) {
      return;
    }

    const reason = prompt('Motivo da reprovação (opcional):') ?? undefined;
    this.service.reject(id, reason).subscribe(() => this.load());
  }

  private load(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    this.service.find(id).subscribe({
      next: (fn) => {
        this.fn.set(fn);
        this.loading.set(false);
      },
      error: () => {
        this.notFound.set(true);
        this.loading.set(false);
      },
    });
  }
}
