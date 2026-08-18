import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '../../core/services/api.service';
import { Paginated } from '../../core/models/api-response.model';
import { StoreUserPayload, UpdateUserPayload, User } from '../../core/models/user.model';

@Injectable({ providedIn: 'root' })
export class UserService {
  private readonly api = inject(ApiService);

  list(): Observable<Paginated<User>> {
    return this.api.get<Paginated<User>>('/users');
  }

  create(payload: StoreUserPayload): Observable<User> {
    return this.api.post<User>('/users', payload);
  }

  update(id: number, payload: Partial<UpdateUserPayload>): Observable<User> {
    return this.api.put<User>(`/users/${id}`, payload);
  }

  toggleActive(id: number): Observable<User> {
    return this.api.patch<User>(`/users/${id}/active`);
  }
}
