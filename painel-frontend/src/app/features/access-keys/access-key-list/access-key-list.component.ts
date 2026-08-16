import { Component, OnInit, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { AccessKeyService } from '../access-key.service';
import { HttpErrorResponse } from '@angular/common/http';
import { ChatAccessKey, ErpConnectionTest, ErpExecutionKey } from '../../../core/models/access-key.model';
import { ChatIntegrationGuideComponent } from '../chat-integration-guide/chat-integration-guide.component';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconDeleteButtonComponent } from '../../../shared/components/icon-delete-button/icon-delete-button.component';
import { IconToggleActiveButtonComponent } from '../../../shared/components/icon-toggle-active-button/icon-toggle-active-button.component';

@Component({
  selector: 'app-access-key-list',
  imports: [
    DatePipe,
    ChatIntegrationGuideComponent,
    EmptyStateComponent,
    PageHeaderComponent,
    IconDeleteButtonComponent,
    IconToggleActiveButtonComponent,
  ],
  templateUrl: './access-key-list.component.html',
  styleUrl: './access-key-list.component.scss',
})
export class AccessKeyListComponent implements OnInit {
  private readonly service = inject(AccessKeyService);

  protected readonly chatKeys = signal<ChatAccessKey[]>([]);
  protected readonly erpKeys = signal<ErpExecutionKey[]>([]);
  protected readonly newlyCreatedKey = signal<string | null>(null);
  protected readonly chatKeyError = signal<string | null>(null);
  protected readonly erpKeyError = signal<string | null>(null);
  protected readonly newErpDescription = signal('');
  protected readonly newErpApiUrl = signal('');
  protected readonly erpFormError = signal<string | null>(null);

  /** Chave cuja URL está sendo editada na tabela, e o valor em edição. */
  protected readonly editingUrlKeyId = signal<number | null>(null);
  protected readonly editingUrl = signal('');

  /** Resultado do último "Testar conexão" de cada chave. */
  protected readonly connectionTests = signal<Record<number, ErpConnectionTest | 'testing'>>({});

  ngOnInit(): void {
    this.loadChatKeys();
    this.loadErpKeys();
  }

  private loadChatKeys(): void {
    this.service.listChatKeys().subscribe((res) => this.chatKeys.set(res.data));
  }

  private loadErpKeys(): void {
    this.service.listErpKeys().subscribe((res) => this.erpKeys.set(res.data));
  }

  protected createChatKey(): void {
    this.service.createChatKey().subscribe((key) => {
      this.newlyCreatedKey.set(key.access_key);
      this.loadChatKeys();
    });
  }

  protected toggleChatKey(key: ChatAccessKey): void {
    this.chatKeyError.set(null);
    this.service.toggleChatKey(key.id, !key.active).subscribe({
      next: () => this.loadChatKeys(),
      error: (err: HttpErrorResponse) => this.chatKeyError.set(errorMessage(err, 'Não foi possível alterar a chave.')),
    });
  }

  protected deleteChatKey(id: number): void {
    if (!confirm('Remover esta chave?')) return;
    this.chatKeyError.set(null);
    this.service.deleteChatKey(id).subscribe({
      next: () => this.loadChatKeys(),
      error: (err: HttpErrorResponse) => this.chatKeyError.set(errorMessage(err, 'Não foi possível remover a chave.')),
    });
  }

  protected createErpKey(): void {
    const description = this.newErpDescription().trim();
    const apiUrl = this.newErpApiUrl().trim();

    if (!description || !apiUrl) {
      this.erpFormError.set('Informe a descrição e a URL da API do plugin.');
      return;
    }
    if (!isHttpUrl(apiUrl)) {
      this.erpFormError.set('A URL precisa começar com http:// ou https://.');
      return;
    }

    this.erpFormError.set(null);
    this.service.createErpKey(description, apiUrl).subscribe({
      next: (key) => {
        this.newlyCreatedKey.set(key.access_key);
        this.newErpDescription.set('');
        this.newErpApiUrl.set('');
        this.loadErpKeys();
      },
      error: (err: HttpErrorResponse) => this.erpFormError.set(errorMessage(err, 'Não foi possível gerar a chave.')),
    });
  }

  protected startEditUrl(key: ErpExecutionKey): void {
    this.editingUrlKeyId.set(key.id);
    this.editingUrl.set(key.api_url);
  }

  protected cancelEditUrl(): void {
    this.editingUrlKeyId.set(null);
  }

  protected saveUrl(key: ErpExecutionKey): void {
    const apiUrl = this.editingUrl().trim();
    if (!isHttpUrl(apiUrl)) return;

    this.service.updateErpKeyUrl(key.id, apiUrl).subscribe(() => {
      this.editingUrlKeyId.set(null);
      this.clearTest(key.id);
      this.loadErpKeys();
    });
  }

  protected testConnection(key: ErpExecutionKey): void {
    this.connectionTests.update((t) => ({ ...t, [key.id]: 'testing' }));
    this.service.testErpConnection(key.id).subscribe({
      next: (result) => this.connectionTests.update((t) => ({ ...t, [key.id]: result })),
      error: () =>
        this.connectionTests.update((t) => ({
          ...t,
          [key.id]: { ok: false, http_status: null, functions: null, message: 'Falha ao testar a conexão.' },
        })),
    });
  }

  protected testResult(id: number): ErpConnectionTest | null {
    const result = this.connectionTests()[id];
    return result && result !== 'testing' ? result : null;
  }

  protected isTesting(id: number): boolean {
    return this.connectionTests()[id] === 'testing';
  }

  private clearTest(id: number): void {
    this.connectionTests.update((t) => {
      const { [id]: _removed, ...rest } = t;
      return rest;
    });
  }

  protected toggleErpKey(key: ErpExecutionKey): void {
    this.erpKeyError.set(null);
    this.service.toggleErpKey(key.id, !key.active).subscribe({
      next: () => this.loadErpKeys(),
      error: (err: HttpErrorResponse) => this.erpKeyError.set(errorMessage(err, 'Não foi possível alterar a chave.')),
    });
  }

  protected deleteErpKey(id: number): void {
    if (!confirm('Remover esta chave?')) return;
    this.erpKeyError.set(null);
    this.service.deleteErpKey(id).subscribe({
      next: () => {
        this.clearTest(id);
        this.loadErpKeys();
      },
      error: (err: HttpErrorResponse) => this.erpKeyError.set(errorMessage(err, 'Não foi possível remover a chave.')),
    });
  }
}

function isHttpUrl(value: string): boolean {
  try {
    const url = new URL(value);
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}

function errorMessage(err: HttpErrorResponse, fallback: string): string {
  return err.error?.error?.message ?? fallback;
}
