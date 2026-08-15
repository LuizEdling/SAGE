import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ComponentService } from '../component.service';
import { ComponentImage } from '../../../core/models/component.model';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

interface PendingImage {
  file: File;
  previewUrl: string;
}

@Component({
  selector: 'app-component-form',
  imports: [ReactiveFormsModule, RouterLink, PageHeaderComponent],
  templateUrl: './component-form.component.html',
})
export class ComponentFormComponent implements OnInit, OnDestroy {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(ComponentService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly saving = signal(false);
  protected readonly loading = signal(false);
  protected readonly uploading = signal(false);
  // Telas já gravadas no módulo (só existem depois que o módulo tem id).
  protected readonly images = signal<ComponentImage[]>([]);
  // Telas escolhidas antes do módulo existir: sobem logo após a criação.
  protected readonly pendingImages = signal<PendingImage[]>([]);
  protected id: number | null = null;

  protected readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    description: ['', Validators.required],
    route: [''],
  });

  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');

    if (!idParam) {
      return;
    }

    this.id = Number(idParam);
    this.loading.set(true);

    this.service.find(this.id).subscribe({
      next: (component) => {
        this.form.patchValue({
          name: component.name,
          description: component.description,
          route: component.route ?? '',
        });
        this.images.set(component.images);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  ngOnDestroy(): void {
    this.pendingImages().forEach((p) => URL.revokeObjectURL(p.previewUrl));
  }

  protected onImagesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    if (files.length === 0) {
      return;
    }

    if (this.id) {
      this.uploading.set(true);
      this.uploadImages(this.id, files, 0);
      return;
    }

    const pending = files.map((file) => ({ file, previewUrl: URL.createObjectURL(file) }));
    this.pendingImages.update((imgs) => [...imgs, ...pending]);
  }

  private uploadImages(componentId: number, files: File[], index: number): void {
    if (index >= files.length) {
      this.uploading.set(false);
      return;
    }

    this.service.uploadImage(componentId, files[index]).subscribe({
      next: (image) => {
        this.images.update((imgs) => [...imgs, image]);
        this.uploadImages(componentId, files, index + 1);
      },
      error: () => this.uploadImages(componentId, files, index + 1),
    });
  }

  protected removeImage(image: ComponentImage): void {
    if (!this.id) {
      return;
    }

    this.service.deleteImage(this.id, image.id).subscribe(() => {
      this.images.update((imgs) => imgs.filter((i) => i.id !== image.id));
    });
  }

  protected removePendingImage(pending: PendingImage): void {
    URL.revokeObjectURL(pending.previewUrl);
    this.pendingImages.update((imgs) => imgs.filter((p) => p !== pending));
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    const raw = this.form.getRawValue();
    const payload = { name: raw.name, description: raw.description, route: raw.route || null };

    if (this.id) {
      this.service.update(this.id, payload).subscribe({
        next: () => this.router.navigate(['/modulos']),
        error: () => this.saving.set(false),
      });
      return;
    }

    this.service.create(payload).subscribe({
      next: (component) => this.uploadPendingThenLeave(component.id, 0),
      error: () => this.saving.set(false),
    });
  }

  /** Só sai da tela depois de subir as telas escolhidas antes de o módulo existir. */
  private uploadPendingThenLeave(componentId: number, index: number): void {
    const pending = this.pendingImages();

    if (index >= pending.length) {
      this.router.navigate(['/modulos']);
      return;
    }

    this.service.uploadImage(componentId, pending[index].file).subscribe({
      next: () => this.uploadPendingThenLeave(componentId, index + 1),
      error: () => this.uploadPendingThenLeave(componentId, index + 1),
    });
  }
}
