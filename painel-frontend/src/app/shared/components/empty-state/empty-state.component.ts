import { Component, input } from '@angular/core';

@Component({
  selector: 'app-empty-state',
  template: `
    <div class="empty-state">
      <p>{{ message() }}</p>
      <ng-content select="[action]" />
    </div>
  `,
  styles: [
    `
      .empty-state {
        padding: 2.5rem 1rem;
        text-align: center;
        color: var(--sage-text-muted);
      }

      .empty-state ::ng-deep [action] {
        display: inline-block;
        margin-top: 0.75rem;
      }
    `,
  ],
})
export class EmptyStateComponent {
  readonly message = input('Nenhum registro encontrado.');
}
