import { Injectable, inject } from '@angular/core';
import { EMPTY, Observable, expand, reduce } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import { Component, ComponentImage, ComponentPayload } from '../../core/models/component.model';
import { Paginated } from '../../core/models/api-response.model';

@Injectable({ providedIn: 'root' })
export class ComponentService {
  private readonly api = inject(ApiService);

  list(page?: number): Observable<Paginated<Component>> {
    return this.api.get<Paginated<Component>>('/components', { page });
  }

  /** Todos os módulos, percorrendo as páginas (o backend pagina de 15 em 15). */
  listAll(): Observable<Component[]> {
    return this.list(1).pipe(
      expand((res) =>
        res.meta.current_page < res.meta.last_page ? this.list(res.meta.current_page + 1) : EMPTY,
      ),
      reduce((all, res) => all.concat(res.data), [] as Component[]),
    );
  }

  find(id: number): Observable<Component> {
    return this.api.get<Component>(`/components/${id}`);
  }

  create(payload: ComponentPayload): Observable<Component> {
    return this.api.post<Component>('/components', payload);
  }

  update(id: number, payload: ComponentPayload): Observable<Component> {
    return this.api.put<Component>(`/components/${id}`, payload);
  }

  delete(id: number): Observable<void> {
    return this.api.delete<void>(`/components/${id}`);
  }

  uploadImage(componentId: number, file: File, caption?: string): Observable<ComponentImage> {
    const formData = new FormData();
    formData.append('image', file);
    if (caption) {
      formData.append('caption', caption);
    }

    return this.api.postForm<ComponentImage>(`/components/${componentId}/images`, formData);
  }

  deleteImage(componentId: number, imageId: number): Observable<void> {
    return this.api.delete<void>(`/components/${componentId}/images/${imageId}`);
  }
}
