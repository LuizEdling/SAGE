import { Component, input, output } from '@angular/core';
import { Paginated } from '../../../core/models/api-response.model';

/**
 * Navegação entre páginas das listagens (o backend pagina de 15 em 15). Sem
 * isto, só os 15 registros mais recentes ficavam visíveis — e um gestor não
 * conseguia aprovar o que estivesse nas páginas seguintes.
 */
@Component({
  selector: 'app-pagination',
  template: `
    @if (meta().last_page > 1) {
      <nav class="pagination" aria-label="Paginação">
        <button
          type="button"
          class="btn btn-sm btn-secondary"
          [disabled]="meta().current_page <= 1"
          (click)="pageChange.emit(meta().current_page - 1)"
        >
          Anterior
        </button>
        <span class="pagination-status mono-value">
          página {{ meta().current_page }} de {{ meta().last_page }} · {{ meta().total }} registros
        </span>
        <button
          type="button"
          class="btn btn-sm btn-secondary"
          [disabled]="meta().current_page >= meta().last_page"
          (click)="pageChange.emit(meta().current_page + 1)"
        >
          Próxima
        </button>
      </nav>
    }
  `,
  styles: [
    `
      .pagination {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      .pagination-status {
        color: var(--sage-text-muted);
      }
    `,
  ],
})
export class PaginationComponent {
  readonly meta = input.required<Paginated<unknown>['meta']>();
  readonly pageChange = output<number>();
}
