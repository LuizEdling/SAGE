import { Component, output } from '@angular/core';

@Component({
  selector: 'app-icon-approve-button',
  template: `
    <a class="icon-btn icon-btn-success" (click)="approve.emit()" title="Aprovar" aria-label="Aprovar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12" />
      </svg>
    </a>
  `,
})
export class IconApproveButtonComponent {
  readonly approve = output<void>();
}
