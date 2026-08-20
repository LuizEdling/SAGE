import { Component, input, signal } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { MessageFonte } from '../../../core/models/conversation.model';
import { IconHelpComponent } from '../icon-help/icon-help.component';

// Limiar de confiança do Agente (ver `confianca_minima` em config.py): abaixo
// disso o diagnóstico é considerado fraco e dispara o ciclo de refinamento.
// Reaproveitado aqui como o corte entre "relevância moderada" e "alta" — é o
// mesmo número, não uma escala nova inventada pra UI.
const RELEVANCIA_ALTA = 0.55;

@Component({
  selector: 'app-evidence-trail',
  imports: [IconHelpComponent, DecimalPipe, RouterLink],
  template: `
    @if (fontes().length > 0) {
      <div class="evidence-trail">
        <button
          type="button"
          class="evidence-trail-toggle"
          (click)="expandido.set(!expandido())"
          [attr.aria-expanded]="expandido()"
        >
          <span class="evidence-trail-toggle-icon" [class.is-open]="expandido()">▸</span>
          {{ expandido() ? 'Ocultar fontes' : 'Ver fontes' }} ({{ fontes().length }})
        </button>

        @if (expandido()) {
          <div class="evidence-trail-body">
            <p class="evidence-trail-label">
              Trilha de evidência
              <app-icon-help
                text="Registros da base de conhecimento usados nesta resposta. O selo mostra o quanto cada um bateu com a pergunta do usuário."
              />
            </p>
            @for (fonte of fontes(); track fonte.knowledge_id) {
              <div class="evidence-trail-row">
                <span class="evidence-leader">⌐</span>
                <span class="evidence-modulo">{{ fonte.modulo }}</span>
                <a
                  class="evidence-id"
                  [routerLink]="['/conhecimentos', fonte.knowledge_id, 'editar']"
                  >#{{ fonte.knowledge_id }}</a
                >
                <span class="evidence-relevance" [class.is-alta]="fonte.score >= relevanciaAlta">
                  {{ fonte.score >= relevanciaAlta ? 'Alta relevância' : 'Relevância moderada' }}
                  · {{ fonte.score * 100 | number: '1.0-0' }}%
                </span>
              </div>
            }
          </div>
        }
      </div>
    }
  `,
  styles: [
    `
      .evidence-trail {
        margin-top: 0.6rem;
        padding-top: 0.6rem;
        border-top: 1px dashed var(--sage-border);
      }

      .evidence-trail-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0;
        border: none;
        background: none;
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: var(--sage-text-muted);
        cursor: pointer;

        &:hover,
        &:focus-visible {
          color: var(--sage-signal);
        }
      }

      .evidence-trail-toggle-icon {
        display: inline-block;
        transition: transform 0.15s ease;

        &.is-open {
          transform: rotate(90deg);
        }
      }

      .evidence-trail-body {
        margin-top: 0.5rem;
      }

      .evidence-trail-label {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        margin: 0 0 0.35rem;
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--sage-signal);
      }

      .evidence-trail-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: var(--font-mono);
        font-size: var(--fs-meta);
        font-variant-numeric: tabular-nums;
        color: var(--sage-text-muted);
        line-height: 1.7;
      }

      .evidence-leader {
        color: var(--sage-signal);
        flex-shrink: 0;
      }

      .evidence-modulo {
        color: var(--sage-text);
      }

      .evidence-id {
        color: var(--sage-text-muted);

        &:hover,
        &:focus-visible {
          color: var(--sage-signal);
        }
      }

      .evidence-relevance {
        display: inline-flex;
        align-items: center;
        margin-left: auto;
        padding: 0.05rem 0.5rem;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.02em;
        white-space: nowrap;
        color: var(--sage-warning);
        background: var(--sage-warning-bg);

        &.is-alta {
          color: var(--sage-success);
          background: var(--sage-success-bg);
        }
      }
    `,
  ],
})
export class EvidenceTrailComponent {
  readonly fontes = input.required<MessageFonte[]>();
  protected readonly expandido = signal(false);
  protected readonly relevanciaAlta = RELEVANCIA_ALTA;
}
