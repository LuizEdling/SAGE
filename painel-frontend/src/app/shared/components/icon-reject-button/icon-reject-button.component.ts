import { Component, output } from '@angular/core';

@Component({
  selector: 'app-icon-reject-button',
  template: `
    <a class="icon-btn icon-btn-danger" (click)="reject.emit()" title="Reprovar" aria-label="Reprovar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
      </svg>
    </a>
  `,
})
export class IconRejectButtonComponent {
  readonly reject = output<void>();
}
