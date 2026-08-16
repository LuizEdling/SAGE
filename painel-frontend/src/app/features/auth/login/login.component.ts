import { Component, ElementRef, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss',
})
export class LoginComponent {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  protected readonly loading = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  protected submit(): void {
    this.syncAutofill();

    if (this.form.invalid) {
      // Os erros aparecem embaixo de cada campo.
      this.form.markAllAsTouched();
      this.errorMessage.set(null);
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);

    const { email, password } = this.form.getRawValue();

    this.authService.login(email, password).subscribe({
      next: () => {
        this.loading.set(false);
        this.router.navigate(['/']);
      },
      error: () => {
        this.loading.set(false);
        this.errorMessage.set('E-mail ou senha inválidos.');
      },
    });
  }

  /**
   * O autopreenchimento do navegador (Chrome/Safari) às vezes mostra os valores
   * nos inputs sem disparar o evento `input`, deixando o FormControl vazio e o
   * formulário inválido. Copia o valor real do DOM para o form antes de validar.
   */
  private syncAutofill(): void {
    for (const name of ['email', 'password'] as const) {
      const input = this.host.nativeElement.querySelector<HTMLInputElement>(`#${name}`);
      const control = this.form.controls[name];
      if (input && input.value && input.value !== control.value) {
        control.setValue(input.value);
      }
    }
  }
}
