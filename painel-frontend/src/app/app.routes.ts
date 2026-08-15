import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { guestGuard } from './core/guards/guest.guard';
import { masterGuard } from './core/guards/master.guard';
import { ShellComponent } from './layout/shell/shell.component';

export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () =>
      import('./features/auth/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    component: ShellComponent,
    canActivate: [authGuard],
    children: [
      {
        path: '',
        pathMatch: 'full',
        loadComponent: () =>
          import('./features/dashboard/dashboard.component').then((m) => m.DashboardComponent),
      },
      {
        path: 'perfil',
        loadComponent: () =>
          import('./features/profile/profile.component').then((m) => m.ProfileComponent),
      },
      {
        path: 'modulos',
        loadComponent: () =>
          import('./features/components/component-list/component-list.component').then(
            (m) => m.ComponentListComponent,
          ),
      },
      {
        path: 'modulos/novo',
        loadComponent: () =>
          import('./features/components/component-form/component-form.component').then(
            (m) => m.ComponentFormComponent,
          ),
      },
      {
        path: 'modulos/:id',
        loadComponent: () =>
          import('./features/components/component-form/component-form.component').then(
            (m) => m.ComponentFormComponent,
          ),
      },
      {
        path: 'conhecimentos',
        loadComponent: () =>
          import('./features/knowledge/knowledge-list/knowledge-list.component').then(
            (m) => m.KnowledgeListComponent,
          ),
      },
      {
        path: 'conhecimentos/novo',
        loadComponent: () =>
          import('./features/knowledge/knowledge-form/knowledge-form.component').then(
            (m) => m.KnowledgeFormComponent,
          ),
      },
      {
        path: 'conhecimentos/importar',
        loadComponent: () =>
          import('./features/knowledge/knowledge-import/knowledge-import.component').then(
            (m) => m.KnowledgeImportComponent,
          ),
      },
      {
        path: 'conhecimentos/:id/editar',
        loadComponent: () =>
          import('./features/knowledge/knowledge-form/knowledge-form.component').then(
            (m) => m.KnowledgeFormComponent,
          ),
      },
      {
        path: 'funcoes',
        loadComponent: () =>
          import('./features/functions/function-list/function-list.component').then(
            (m) => m.FunctionListComponent,
          ),
      },
      {
        path: 'funcoes/guia',
        loadComponent: () =>
          import('./features/functions/function-guide/function-guide.component').then(
            (m) => m.FunctionGuideComponent,
          ),
      },
      {
        path: 'funcoes/:id',
        loadComponent: () =>
          import('./features/functions/function-detail/function-detail.component').then(
            (m) => m.FunctionDetailComponent,
          ),
      },
      {
        path: 'chat-teste',
        canActivate: [masterGuard],
        loadComponent: () => import('./features/chat/chat.component').then((m) => m.ChatComponent),
      },
      {
        path: 'usuarios',
        canActivate: [masterGuard],
        loadComponent: () =>
          import('./features/users/user-list/user-list.component').then((m) => m.UserListComponent),
      },
      {
        path: 'usuarios/novo',
        canActivate: [masterGuard],
        loadComponent: () =>
          import('./features/users/user-form/user-form.component').then((m) => m.UserFormComponent),
      },
      {
        path: 'usuarios/:id',
        canActivate: [masterGuard],
        loadComponent: () =>
          import('./features/users/user-form/user-form.component').then((m) => m.UserFormComponent),
      },
      {
        path: 'chaves-acesso',
        canActivate: [masterGuard],
        loadComponent: () =>
          import('./features/access-keys/access-key-list/access-key-list.component').then(
            (m) => m.AccessKeyListComponent,
          ),
      },
      {
        path: 'conversas',
        loadComponent: () =>
          import('./features/conversations/conversation-list/conversation-list.component').then(
            (m) => m.ConversationListComponent,
          ),
      },
      {
        path: 'execucoes',
        loadComponent: () =>
          import('./features/executions/execution-list/execution-list.component').then(
            (m) => m.ExecutionListComponent,
          ),
      },
      {
        path: 'conversas/:id',
        loadComponent: () =>
          import('./features/conversations/conversation-detail/conversation-detail.component').then(
            (m) => m.ConversationDetailComponent,
          ),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
