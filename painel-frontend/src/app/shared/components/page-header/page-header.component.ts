import { Component, input } from '@angular/core';

@Component({
  selector: 'app-page-header',
  template: `
    <div class="page-header-block">
      <p class="eyebrow">{{ eyebrow() }}</p>
      <div class="page-header-top">
        <h1 class="page-title">{{ title() }}</h1>
        <div class="page-header-actions">
          @if (badge()) {
            <span class="pill-badge">{{ badge() }}</span>
          }
          <ng-content select="[actions]" />
        </div>
      </div>

      <div class="page-header-rule"></div>

      @if (showFilters()) {
        <div class="page-header-filters">
          <ng-content select="[filters]" />
        </div>
      }
    </div>
  `,
  styles: [
    `
      .page-header-block {
        margin-bottom: 1.5rem;
      }

      .eyebrow {
        margin: 0 0 0.35rem;
        font-family: var(--font-mono);
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--sage-text-muted);
      }

      .page-header-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
      }

      .page-title {
        margin: 0;
      }

      .page-header-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
      }

      .page-header-rule {
        height: 1px;
        background: var(--sage-border-strong);
        margin-top: 1rem;
      }

      .page-header-filters {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 1rem;
      }
    `,
  ],
})
export class PageHeaderComponent {
  readonly eyebrow = input('');
  readonly title = input('');
  readonly badge = input<string | null>(null);
  readonly showFilters = input(false);
}
