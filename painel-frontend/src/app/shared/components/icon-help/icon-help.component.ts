import { Component, input } from '@angular/core';

let nextId = 0;

@Component({
  selector: 'app-icon-help',
  template: `
    <span class="icon-help" tabindex="0" [attr.aria-describedby]="tooltipId">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10" />
        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4" />
        <path d="M12 17h.01" />
      </svg>
      <span class="icon-help-tooltip" role="tooltip" [id]="tooltipId">{{ text() }}</span>
    </span>
  `,
})
export class IconHelpComponent {
  readonly text = input.required<string>();
  protected readonly tooltipId = `icon-help-tooltip-${nextId++}`;
}
