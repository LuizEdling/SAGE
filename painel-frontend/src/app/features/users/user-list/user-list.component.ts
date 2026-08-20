import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { UserService } from '../user.service';
import { User } from '../../../core/models/user.model';
import { AuthService } from '../../../core/services/auth.service';
import { EmptyStateComponent } from '../../../shared/components/empty-state/empty-state.component';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';
import { IconEditLinkComponent } from '../../../shared/components/icon-edit-link/icon-edit-link.component';
import { IconToggleActiveButtonComponent } from '../../../shared/components/icon-toggle-active-button/icon-toggle-active-button.component';

@Component({
  selector: 'app-user-list',
  imports: [
    RouterLink,
    EmptyStateComponent,
    PageHeaderComponent,
    IconEditLinkComponent,
    IconToggleActiveButtonComponent,
  ],
  templateUrl: './user-list.component.html',
})
export class UserListComponent implements OnInit {
  private readonly service = inject(UserService);
  private readonly authService = inject(AuthService);

  protected readonly items = signal<User[]>([]);
  protected readonly loading = signal(true);
  protected readonly currentUserId = () => this.authService.currentUser()?.id;

  protected readonly roleFilter = signal<boolean | null>(null);

  protected readonly filteredItems = computed(() => {
    const role = this.roleFilter();
    return role === null ? this.items() : this.items().filter((u) => u.is_master === role);
  });

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

  protected toggleActive(user: User): void {
    this.service.toggleActive(user.id).subscribe(() => this.load());
  }
}
