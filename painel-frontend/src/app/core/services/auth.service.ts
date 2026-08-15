import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { ApiService } from './api.service';
import { TokenStorageService } from './token-storage.service';
import { User } from '../models/user.model';

interface LoginResponse {
  token: string;
  user: User;
}

export interface UpdateProfilePayload {
  name: string;
  email: string;
  current_password?: string;
  password?: string;
  password_confirmation?: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiService);
  private readonly tokenStorage = inject(TokenStorageService);

  private readonly currentUserSignal = signal<User | null>(null);

  readonly currentUser = this.currentUserSignal.asReadonly();
  readonly isAuthenticated = computed(() => this.currentUserSignal() !== null);
  readonly isMaster = computed(() => this.currentUserSignal()?.is_master ?? false);

  login(email: string, password: string): Observable<LoginResponse> {
    return this.api.post<LoginResponse>('/auth/login', { email, password }).pipe(
      tap((res) => {
        this.tokenStorage.set(res.token);
        this.currentUserSignal.set(res.user);
      }),
    );
  }

  logout(): Observable<void> {
    return this.api.post<void>('/auth/logout').pipe(
      tap(() => {
        this.tokenStorage.clear();
        this.currentUserSignal.set(null);
      }),
    );
  }

  loadCurrentUser(): Observable<User> {
    return this.api.get<User>('/auth/me').pipe(tap((user) => this.currentUserSignal.set(user)));
  }

  updateProfile(payload: UpdateProfilePayload): Observable<User> {
    return this.api.put<User>('/auth/me', payload).pipe(tap((user) => this.currentUserSignal.set(user)));
  }

  hasToken(): boolean {
    return this.tokenStorage.get() !== null;
  }

  clearSession(): void {
    this.tokenStorage.clear();
    this.currentUserSignal.set(null);
  }
}
