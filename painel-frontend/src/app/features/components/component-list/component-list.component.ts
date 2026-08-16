import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ComponentService } from '../component.service';
import { Component as ComponentModel } from '../../../core/models/component.model';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconEditLinkComponent } from '../../../shared/components/icon-edit-link/icon-edit-link.component';
import { IconDeleteButtonComponent } from '../../../shared/components/icon-delete-button/icon-delete-button.component';

@Component({
  selector: 'app-component-list',
  imports: [
    RouterLink,
    EmptyStateComponent,
    PageHeaderComponent,
    IconEditLinkComponent,
    IconDeleteButtonComponent,
  ],
  templateUrl: './component-list.component.html',
})
export class ComponentListComponent implements OnInit {
  private readonly service = inject(ComponentService);

  protected readonly items = signal<ComponentModel[]>([]);
  protected readonly loading = signal(true);
  // O backend recusa excluir módulo que ainda tem conhecimento ou função
  // vinculada (restrictOnDelete). Sem mostrar o motivo, a exclusão pareceria
  // simplesmente não funcionar.
  protected readonly deleteError = signal<string | null>(null);

  ngOnInit(): void {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.service.list().subscribe({
      next: (res) => {
        this.items.set(res.data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  protected remove(item: ComponentModel): void {
    if (!confirm(`Remover o módulo "${item.name}"?`)) {
      return;
    }

    this.deleteError.set(null);

    this.service.delete(item.id).subscribe({
      next: () => this.load(),
      error: () =>
        this.deleteError.set(
          `Não foi possível remover "${item.name}". Módulos com conhecimento ou funções vinculadas não podem ser excluídos.`,
        ),
    });
  }
}
