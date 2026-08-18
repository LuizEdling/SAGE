import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { AuthService } from '../../core/services/auth.service';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-profile',
  imports: [ReactiveFormsModule, PageHeaderComponent],
  templateUrl: './profile.component.html',
  styleUrl: './profile.component.scss',
})
export class ProfileComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);

  protected readonly currentUser = this.authService.currentUser;
  protected readonly isMaster = this.authService.isMaster;

  protected readonly initial = computed(() => this.currentUser()?.name?.charAt(0)?.toUpperCase() ?? '');

  protected readonly saving = signal(false);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly changingPassword = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    current_password: [''],
    password: [''],
    password_confirmation: [''],
  });

  ngOnInit(): void {
    const user = this.currentUser();

    if (user) {
      this.form.patchValue({ name: user.name, email: user.email });
    }
  }

  protected togglePasswordFields(): void {
    this.changingPassword.set(!this.changingPassword());
    this.form.patchValue({ current_password: '', password: '', password_confirmation: '' });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();

    if (this.changingPassword() && raw.password !== raw.password_confirmation) {
      this.errorMessage.set('A confirmação de senha não bate com a nova senha.');
      return;
    }

    this.saving.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    const payload = {
      name: raw.name,
      email: raw.email,
      ...(this.changingPassword() && raw.password
        ? {
            current_password: raw.current_password,
            password: raw.password,
            password_confirmation: raw.password_confirmation,
          }
        : {}),
    };

    this.authService.updateProfile(payload).subscribe({
      next: () => {
        this.saving.set(false);
        this.successMessage.set('Dados atualizados com sucesso.');
        this.changingPassword.set(false);
        this.form.patchValue({ current_password: '', password: '', password_confirmation: '' });
      },
      error: (err) => {
        this.saving.set(false);
        this.errorMessage.set(err?.error?.error?.message ?? 'Não foi possível salvar as alterações.');
      },
    });
  }
}
