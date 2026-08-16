import { Component, OnInit, inject, signal } from '@angular/core';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { KnowledgeService } from '../knowledge.service';
import { ComponentService } from '../../components/component.service';
import { FunctionService } from '../../functions/function.service';
import { AuthService } from '../../../core/services/auth.service';
import {
  AgentFunction,
  FunctionRecommendationResult,
} from '../../../core/models/agent-function.model';
import { OPERATION_TYPE_LABEL } from '../../../core/models/enums';
import { Component as ComponentModel, ComponentImage } from '../../../core/models/component.model';
import { DuplicateCandidate } from '../../../core/models/knowledge.model';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

interface PendingImage {
  file: File;
  previewUrl: string;
}

@Component({
  selector: 'app-knowledge-form',
  imports: [ReactiveFormsModule, RouterLink, PageHeaderComponent],
  templateUrl: './knowledge-form.component.html',
})
export class KnowledgeFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(KnowledgeService);
  private readonly componentService = inject(ComponentService);
  private readonly functionService = inject(FunctionService);
  protected readonly authService = inject(AuthService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly operationTypeLabel = OPERATION_TYPE_LABEL;
  protected readonly saving = signal(false);
  protected readonly currentStep = signal<1 | 2>(1);
  protected readonly components = signal<ComponentModel[]>([]);
  protected readonly duplicates = signal<DuplicateCandidate[]>([]);
  protected readonly moduleMode = signal<'existing' | 'new'>('existing');
  protected readonly moduleImages = signal<ComponentImage[]>([]);
  protected readonly pendingModuleImages = signal<PendingImage[]>([]);
  protected readonly uploadingModuleImage = signal(false);
  /** Funções do módulo escolhido que podem ser vinculadas (só o gestor vê e altera). */
  protected readonly moduleFunctions = signal<AgentFunction[]>([]);
  protected readonly selectedFunctionIds = signal<number[]>([]);
  private originalFunctionIds: number[] = [];
  protected readonly recommending = signal(false);
  protected readonly recommendation = signal<FunctionRecommendationResult | null>(null);
  protected id: number | null = null;

  protected readonly form = this.fb.nonNullable.group({
    type: ['base', Validators.required],
    content: ['', Validators.required],
    component_id: [null as number | null],
    valid_until: [''],
  });

  protected readonly moduloForm = this.fb.nonNullable.group({
    name: ['', Validators.required],
    description: ['', Validators.required],
    route: [''],
  });

  ngOnInit(): void {
    this.componentService.list().subscribe((res) => {
      this.components.set(res.data);

      const idParam = this.route.snapshot.paramMap.get('id');

      if (idParam) {
        this.id = Number(idParam);
        this.service.find(this.id).subscribe((knowledge) => {
          this.form.patchValue({
            type: knowledge.type,
            content: knowledge.content,
            component_id: knowledge.component?.id ?? null,
            valid_until: knowledge.valid_until?.substring(0, 10) ?? '',
          });

          this.selectedFunctionIds.set(knowledge.function_ids);
          this.originalFunctionIds = knowledge.function_ids;

          if (knowledge.component) {
            this.selectExistingModule(knowledge.component.id);
          }
        });
      }
    });
  }

  protected get isTemporario(): boolean {
    return this.form.controls.type.value === 'temporario';
  }

  protected goToStep2(): void {
    const modeIsNew = this.moduleMode() === 'new';
    const moduleInvalid = modeIsNew
      ? this.moduloForm.invalid
      : !this.form.controls.component_id.value;

    if (moduleInvalid) {
      this.moduloForm.markAllAsTouched();
      return;
    }

    this.currentStep.set(2);
  }

  protected goToStep1(): void {
    this.currentStep.set(1);
  }

  protected setModuleMode(mode: 'existing' | 'new'): void {
    this.moduleMode.set(mode);

    if (mode === 'new') {
      this.form.controls.component_id.setValue(null);
      this.moduloForm.reset({ name: '', description: '', route: '' });
      this.moduleImages.set([]);
      this.pendingModuleImages.set([]);
      // Módulo novo ainda não tem funções.
      this.moduleFunctions.set([]);
      this.selectedFunctionIds.set([]);
    }
  }

  protected onModuleSelected(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const componentId = value ? Number(value) : null;
    this.form.controls.component_id.setValue(componentId);

    if (componentId) {
      this.selectExistingModule(componentId);
    }
  }

  private selectExistingModule(componentId: number): void {
    const component = this.components().find((c) => c.id === componentId);

    if (!component) {
      return;
    }

    this.moduloForm.patchValue({
      name: component.name,
      description: component.description,
      route: component.route ?? '',
    });
    this.moduleImages.set(component.images);
    this.pendingModuleImages.set([]);
    this.checkDuplicate();
    this.loadModuleFunctions(componentId);
  }

  private loadModuleFunctions(componentId: number): void {
    if (!this.authService.isMaster()) {
      return;
    }

    this.functionService.list(undefined, componentId).subscribe((res) => {
      this.moduleFunctions.set(res.data);
      // Vínculo só vale dentro do módulo do conhecimento.
      const ids = res.data.map((f) => f.id);
      this.selectedFunctionIds.update((atual) => atual.filter((id) => ids.includes(id)));
    });
  }

  /** "Recomendar funções": só sugere — vincular continua sendo decisão do gestor. */
  protected recommendFunctions(): void {
    if (!this.id) {
      return;
    }

    this.recommending.set(true);
    this.functionService.recommend(this.id).subscribe({
      next: (result) => {
        this.recommendation.set(result);
        this.recommending.set(false);
      },
      error: () => this.recommending.set(false),
    });
  }

  protected linkRecommended(functionId: number): void {
    this.selectedFunctionIds.update((atual) =>
      atual.includes(functionId) ? atual : [...atual, functionId],
    );
  }

  protected toggleFunction(functionId: number): void {
    this.selectedFunctionIds.update((atual) =>
      atual.includes(functionId) ? atual.filter((id) => id !== functionId) : [...atual, functionId],
    );
  }

  protected onModuleImagesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    input.value = '';

    if (files.length === 0) {
      return;
    }

    const componentId = this.form.controls.component_id.value;

    if (this.moduleMode() === 'existing' && componentId) {
      this.uploadingModuleImage.set(true);
      this.uploadModuleImages(componentId, files, 0);
    } else {
      const pending = files.map((file) => ({ file, previewUrl: URL.createObjectURL(file) }));
      this.pendingModuleImages.update((imgs) => [...imgs, ...pending]);
    }
  }

  private uploadModuleImages(componentId: number, files: File[], index: number): void {
    if (index >= files.length) {
      this.uploadingModuleImage.set(false);
      return;
    }

    this.componentService.uploadImage(componentId, files[index]).subscribe({
      next: (image) => {
        this.moduleImages.update((imgs) => [...imgs, image]);
        this.uploadModuleImages(componentId, files, index + 1);
      },
      error: () => this.uploadModuleImages(componentId, files, index + 1),
    });
  }

  protected removeModuleImage(image: ComponentImage): void {
    const componentId = this.form.controls.component_id.value;

    if (!componentId) {
      return;
    }

    this.componentService.deleteImage(componentId, image.id).subscribe(() => {
      this.moduleImages.update((imgs) => imgs.filter((i) => i.id !== image.id));
    });
  }

  protected removePendingModuleImage(pending: PendingImage): void {
    URL.revokeObjectURL(pending.previewUrl);
    this.pendingModuleImages.update((imgs) => imgs.filter((p) => p !== pending));
  }

  protected checkDuplicate(): void {
    const { component_id, content } = this.form.getRawValue();

    if (!component_id || !content) {
      return;
    }

    this.service.checkDuplicate(component_id, content).subscribe((res) => {
      this.duplicates.set(res.duplicates.filter((d) => d.knowledge_id !== this.id));
    });
  }

  protected submit(): void {
    const modeIsNew = this.moduleMode() === 'new';
    const moduleInvalid = modeIsNew
      ? this.moduloForm.invalid
      : !this.form.controls.component_id.value;

    if (this.form.invalid || moduleInvalid) {
      this.form.markAllAsTouched();
      this.moduloForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);

    if (modeIsNew) {
      const raw = this.moduloForm.getRawValue();
      this.componentService
        .create({ name: raw.name, description: raw.description, route: raw.route || null })
        .subscribe({
          next: (component) => this.uploadModulePendingThenSaveKnowledge(component.id, 0),
          error: () => this.saving.set(false),
        });
      return;
    }

    const componentId = this.form.controls.component_id.value;

    if (!componentId) {
      this.saving.set(false);
      return;
    }

    this.maybeUpdateModuleRoute(componentId, () => this.saveKnowledge(componentId));
  }

  private maybeUpdateModuleRoute(componentId: number, next: () => void): void {
    const component = this.components().find((c) => c.id === componentId);
    const raw = this.moduloForm.getRawValue();

    if (component && component.route === (raw.route || null)) {
      next();
      return;
    }

    this.componentService
      .update(componentId, {
        name: raw.name,
        description: raw.description,
        route: raw.route || null,
      })
      .subscribe({
        next: () => next(),
        error: () => next(),
      });
  }

  private uploadModulePendingThenSaveKnowledge(componentId: number, index: number): void {
    const pending = this.pendingModuleImages();

    if (index >= pending.length) {
      this.saveKnowledge(componentId);
      return;
    }

    this.componentService.uploadImage(componentId, pending[index].file).subscribe({
      next: () => this.uploadModulePendingThenSaveKnowledge(componentId, index + 1),
      error: () => this.uploadModulePendingThenSaveKnowledge(componentId, index + 1),
    });
  }

  private saveKnowledge(componentId: number): void {
    const raw = this.form.getRawValue();
    const payload = {
      type: raw.type as 'base' | 'temporario',
      content: raw.content,
      component_id: componentId,
      valid_until: raw.type === 'temporario' && raw.valid_until ? raw.valid_until : null,
    };

    const request = this.id ? this.service.update(this.id, payload) : this.service.create(payload);

    request.subscribe({
      next: (knowledge) => this.saveFunctionsThenLeave(knowledge.id),
      error: () => this.saving.set(false),
    });
  }

  private saveFunctionsThenLeave(knowledgeId: number): void {
    const selecionadas = [...this.selectedFunctionIds()].sort();
    const mudou = selecionadas.join(',') !== [...this.originalFunctionIds].sort().join(',');

    if (!this.authService.isMaster() || !mudou) {
      this.router.navigate(['/conhecimentos']);
      return;
    }

    this.service.updateFunctions(knowledgeId, selecionadas).subscribe({
      next: () => this.router.navigate(['/conhecimentos']),
      error: () => this.saving.set(false),
    });
  }
}
