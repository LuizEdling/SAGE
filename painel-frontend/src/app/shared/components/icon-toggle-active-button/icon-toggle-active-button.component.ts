import { Component, input, output } from '@angular/core';

@Component({
  selector: 'app-icon-toggle-active-button',
  template: `
    <a
      class="icon-btn"
      [class.icon-btn-danger]="active()"
      [class.icon-btn-success]="!active()"
      (click)="toggle.emit()"
      [title]="label()"
      [attr.aria-label]="label()"
    >
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18.36 6.64a9 9 0 1 1-12.73 0" />
        <line x1="12" y1="2" x2="12" y2="12" />
      </svg>
    </a>
  `,
})
export class IconToggleActiveButtonComponent {
  readonly active = input.required<boolean>();
  readonly toggle = output<void>();

  protected label(): string {
    return this.active() ? 'Desativar' : 'Ativar';
  }
}
