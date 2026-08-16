import { Component, input } from '@angular/core';
import { ApprovalStatus, APPROVAL_STATUS_LABEL } from '../../../core/models/enums';

@Component({
  selector: 'app-status-badge',
  template: `<span class="badge status-{{ status() }}">{{ label() }}</span>`,
})
export class StatusBadgeComponent {
  readonly status = input.required<ApprovalStatus>();

  protected label(): string {
    return APPROVAL_STATUS_LABEL[this.status()];
  }
}
