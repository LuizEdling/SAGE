import { Component, OnInit, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { UserService } from '../user.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

@Component({
  selector: 'app-user-form',
  imports: [ReactiveFormsModule, RouterLink, PageHeaderComponent],
  templateUrl: './user-form.component.html',
})
export class UserFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(UserService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected id: number | null = null;

  protected readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: [''],
    is_master: [false],
  });

  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');

    if (idParam) {
      this.id = Number(idParam);
      this.form.controls.password.clearValidators();

      this.service.list().subscribe((res) => {
        const user = res.data.find((u) => u.id === this.id);

        if (user) {
          this.form.patchValue({ name: user.name, email: user.email, is_master: user.is_master });
        }
      });
    } else {
      this.form.controls.password.addValidators([Validators.required, Validators.minLength(8)]);
    }
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.errorMessage.set(null);
    const raw = this.form.getRawValue();

    const request = this.id
      ? this.service.update(this.id, { name: raw.name, email: raw.email, is_master: raw.is_master })
      : this.service.create({ name: raw.name, email: raw.email, password: raw.password, is_master: raw.is_master });

    request.subscribe({
      next: () => this.router.navigate(['/usuarios']),
      error: (err) => {
        this.saving.set(false);
        this.errorMessage.set(err?.error?.error?.message ?? 'Não foi possível salvar o usuário.');
      },
    });
  }
}
