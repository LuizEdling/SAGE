import { Component, computed, input } from '@angular/core';
import { DatePipe, JsonPipe } from '@angular/common';
import { Execution } from '../../../core/models/execution.model';
import { EXECUTION_STATUS_LABEL, OPERATION_TYPE_LABEL } from '../../../core/models/enums';

/**
 * Uma função do sistema legado usada pelo Agente: consulta (autorizada por
 * política) ou alteração (proposta e decidida pelo usuário). Mostra o que foi
 * pedido e o que o sistema legado devolveu — a evidência de que a resposta
 * usou um dado real, ou do que mudou e quem autorizou.
 */
@Component({
  selector: 'app-execution-card',
  imports: [DatePipe, JsonPipe],
  template: `
    <div
      class="execution-card"
      [class.is-alteracao]="execution().function.operation_type === 'alteracao'"
    >
      <div class="execution-card-header">
        <span class="execution-card-name">{{ execution().function.name }}</span>
        <span class="type-pill">{{ tipo() }}</span>
        <span class="badge status-{{ execution().status }}">{{ status() }}</span>
      </div>

      @if (parametros().length > 0) {
        <p class="execution-card-line">
          @for (p of parametros(); track p[0]) {
            <span class="mono-value">{{ p[0] }}={{ p[1] | json }}</span>
          }
        </p>
      }

      @if (mudancas(); as m) {
        <p class="execution-card-line">
          <span class="execution-card-label">antes</span>
          <span class="mono-value">{{ m.antes | json }}</span>
        </p>
        <p class="execution-card-line">
          <span class="execution-card-label">depois</span>
          <span class="mono-value">{{ m.depois | json }}</span>
        </p>
      } @else if (dados(); as d) {
        <p class="execution-card-line">
          <span class="execution-card-label">lido</span>
          <span class="mono-value">{{ d | json }}</span>
        </p>
      }

      @if (execution().error_code) {
        <p class="execution-card-line field-error">{{ execution().error_code }}</p>
      }

      <p class="execution-card-line execution-card-meta">
        {{ origem() }}
        @if (execution().executed_at) {
          · executada em {{ execution().executed_at | date: 'dd/MM HH:mm' }}
        }
      </p>
    </div>
  `,
  styles: [
    `
      .execution-card {
        margin-top: 0.6rem;
        padding: 0.6rem 0.75rem;
        border: 1px solid var(--sage-border);
        border-left: 3px solid var(--sage-signal);
        border-radius: var(--sage-radius-sm);
        background: var(--sage-surface);
        // Dentro da bolha do usuário (fundo de ação, texto claro) o cartão
        // mantém a cor e o alinhamento próprios.
        color: var(--sage-text);
        text-align: left;
        white-space: normal;

        &.is-alteracao {
          border-left-color: var(--sage-action);
        }
      }

      .execution-card-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      .execution-card-name {
        font-weight: 600;
      }

      .execution-card-line {
        margin: 0.35rem 0 0;
        font-size: var(--fs-meta);
        word-break: break-word;

        .mono-value {
          margin-right: 0.5rem;
        }
      }

      .execution-card-label {
        display: inline-block;
        min-width: 3.5rem;
        font-family: var(--font-mono);
        font-size: 10px;
        text-transform: uppercase;
        color: var(--sage-text-muted);
      }

      .execution-card-meta {
        color: var(--sage-text-muted);
      }
    `,
  ],
})
export class ExecutionCardComponent {
  readonly execution = input.required<Execution>();

  protected readonly status = computed(() => EXECUTION_STATUS_LABEL[this.execution().status]);
  protected readonly tipo = computed(() => {
    const tipo = this.execution().function.operation_type;
    return tipo ? OPERATION_TYPE_LABEL[tipo] : '';
  });
  /** Quem decidiu: consulta roda por política; alteração depende do usuário. */
  protected readonly origem = computed(() => {
    const execution = this.execution();

    if (execution.function.operation_type === 'consulta') {
      return 'Consulta automática (sem supervisão)';
    }

    if (execution.status === 'negada') {
      return execution.confirmed_by_user
        ? 'Negada pelo usuário'
        : 'Não autorizada (expirou ou foi substituída)';
    }

    return execution.confirmed_by_user ? 'Autorizada pelo usuário' : 'Aguardando o usuário';
  });

  protected readonly parametros = computed(() => Object.entries(this.execution().parameters ?? {}));

  protected readonly mudancas = computed(() => {
    const result = this.execution().result;
    return result && 'antes' in result && 'depois' in result
      ? { antes: result['antes'], depois: result['depois'] }
      : null;
  });

  protected readonly dados = computed(() => {
    const result = this.execution().result;
    return result && 'dados' in result ? result['dados'] : null;
  });
}
