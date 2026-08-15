import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiSuccess } from '../models/api-response.model';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  get<T>(path: string, params?: Record<string, string | number | undefined>): Observable<T> {
    return this.http
      .get<ApiSuccess<T>>(`${this.baseUrl}${path}`, { params: this.toHttpParams(params) })
      .pipe(map((res) => res.data));
  }

  post<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.post<ApiSuccess<T>>(`${this.baseUrl}${path}`, body).pipe(map((res) => res.data));
  }

  put<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.put<ApiSuccess<T>>(`${this.baseUrl}${path}`, body).pipe(map((res) => res.data));
  }

  patch<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.patch<ApiSuccess<T>>(`${this.baseUrl}${path}`, body).pipe(map((res) => res.data));
  }

  delete<T>(path: string): Observable<T> {
    return this.http.delete<ApiSuccess<T>>(`${this.baseUrl}${path}`).pipe(map((res) => res.data));
  }

  postForm<T>(path: string, formData: FormData): Observable<T> {
    return this.http.post<ApiSuccess<T>>(`${this.baseUrl}${path}`, formData).pipe(map((res) => res.data));
  }

  private toHttpParams(params?: Record<string, string | number | undefined>): HttpParams {
    let httpParams = new HttpParams();

    for (const [key, value] of Object.entries(params ?? {})) {
      if (value !== undefined && value !== null && value !== '') {
        httpParams = httpParams.set(key, value);
      }
    }

    return httpParams;
  }
}
