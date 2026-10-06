import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ComponentService } from '../../components/component.service';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

interface PromptModule {
  name: string;
  description: string;
}

const MANIFESTO_EXEMPLO = `{
  "funcoes": [
    {
      "code": "notificacao.evento.ler",
      "name": "Consultar as notificações de um evento",
      "description": "Lê se as notificações por e-mail de um evento estão ativas. Use quando o usuário diz que não recebe e-mail em alguma situação.",
      "module": "Notificações",
      "operation_type": "consulta",
      "parameters": [
        { "name": "evento", "type": "string", "required": true, "description": "Código do evento (ex.: pedido_aprovado)." }
      ]
    }
  ]
}`;

const PEDIDO_EXEMPLO = `POST {base}/functions/notificacao.evento.ler/execute
X-Sage-Execution-Key: <chave de execução>

{
  "execution_id": 42,
  "usuario": { "id": 7, "login": "maria.silva", "perfil_id": 1, "entidade_id": 1, "recursivo": false },
  "parametros": { "evento": "pedido_aprovado" }
}`;

const RESPOSTA_CONSULTA_EXEMPLO = `HTTP 200
{
  "status": "ok",
  "dados": { "evento": "Pedido aprovado", "notificacoes": [{ "nome": "E-mail ao solicitante", "ativa": false }] },
  "pode_alterar": false
}`;

const RESPOSTA_ALTERACAO_EXEMPLO = `HTTP 200
{
  "status": "ok",
  "antes":  { "evento": "Pedido aprovado", "notificacoes": [{ "nome": "E-mail ao solicitante", "ativa": false }] },
  "depois": { "evento": "Pedido aprovado", "notificacoes": [{ "nome": "E-mail ao solicitante", "ativa": true }] }
}`;

const PROMPT_INTRO = `Você está com acesso ao repositório de código-fonte de um sistema legado. Sua tarefa é
implementar, nesse sistema, as funções que o S.A.G.E. (um assistente de suporte técnico)
poderá executar para conferir e, com autorização do usuário, alterar configurações.

CONTEXTO
O S.A.G.E. responde chamados de suporte. Quando a causa de um problema é uma configuração
("o usuário acha que o sistema falhou, mas a opção estava desligada"), ele pede ao sistema
legado o valor real dessa configuração. Existem dois tipos de função:
- consulta: só lê. Roda sozinha, sem pedir nada ao usuário.
- alteração: muda um valor. Só roda depois que o usuário clica em Autorizar.

O S.A.G.E. não acessa o banco do sistema legado. Ele conversa com uma API que você vai criar.`;

const PROMPT_CONTRATO = `CONTRATO DA API
Escolha um caminho base para a API dentro do sistema (por exemplo, /sage/api). A URL completa
(endereço do sistema + caminho base) é cadastrada no painel do S.A.G.E., em Chaves de acesso,
junto com a chave de execução. Abaixo, {base} é essa URL.
Toda requisição traz o header X-Sage-Execution-Key. Guarde só o SHA-256 da chave
configurada e compare com o hash da recebida; sem chave configurada, rejeite tudo (401).

1. GET {base}/manifest
   Devolve a lista fechada das funções (formato abaixo). É o que o painel importa.
2. POST {base}/functions/{code}/execute
   Executa UMA função da lista. Código fora da lista: 404.
3. GET {base}/ping (opcional)
   Responde 200 se a chave for válida.

Formato do manifesto:
${MANIFESTO_EXEMPLO}

- code: "modulo.assunto.acao", só letras minúsculas, números, ponto e sublinhado.
- module: o nome EXATO de um módulo já cadastrado no painel (lista no PASSO 1).
- operation_type: "consulta" ou "alteracao".
- parameters[].type: string, integer, boolean, date (AAAA-MM-DD) ou float.
- description: é lida pelo Agente para decidir quando usar a função. Diga quando usar e o que
  devolve. Nas alterações, diga que só se usa depois de consultar e confirmar o valor.

Pedido de execução:
${PEDIDO_EXEMPLO}

O objeto "usuario" sempre traz "id" e "login". "perfil_id" (perfil ou papel), "entidade_id"
(empresa, filial ou área) e "recursivo" (acesso às subáreas) são opcionais: use os que existirem
no sistema e ignore os demais.

Nas funções de alteração o pedido também traz "token_autorizacao".

Resposta de uma consulta:
${RESPOSTA_CONSULTA_EXEMPLO}

Resposta de uma alteração:
${RESPOSTA_ALTERACAO_EXEMPLO}

Falhas: devolva o status HTTP e {"status": "<codigo>", "mensagem": "..."}:
401 nao_autorizado, 403 sem_permissao, 404 nao_encontrado, 422 invalido, 500 erro.`;

const PROMPT_REGRAS = `PASSO 2 — ESCOLHER AS CONFIGURAÇÕES
Escolha configurações cujo valor errado faz o usuário achar que o sistema falhou (opções
desligadas, prazos, visibilidade, limites, contas inativas). Para cada uma, crie uma função de
consulta e, quando fizer sentido, uma de alteração. Não crie função para dado pessoal sensível.

PASSO 3 — IMPLEMENTAR COM AS REGRAS DO PRÓPRIO SISTEMA
- Use as classes e a camada de acesso do sistema, nunca SQL direto: assim valem as regras de
  negócio, as validações e o histórico.
- Toda função roda com os direitos do usuário informado em "usuario", não os de um administrador.
  Abra uma sessão temporária desse usuário durante a execução e feche ao terminar.
- Toda consulta devolve "pode_alterar": se ESSE usuário pode alterar aquela configuração, segundo
  a checagem de direitos do próprio sistema.
- Consulta nunca altera nada.
- Alteração só executa com um "token_autorizacao" válido: emitido pelo sistema legado quando o
  usuário clica em Autorizar, de uso único e curta duração, ligado ao usuário e à execução.
- Depois de alterar, leia o valor de novo para confirmar que foi gravado e devolva "antes" e
  "depois". Se não foi gravado, devolva sem_permissao.
- Valide os parâmetros e devolva 422 com uma mensagem clara. Confira que o item pertence à
  empresa, filial ou área que o usuário pode acessar.
- Registre uma auditoria de cada execução (quem, qual função, parâmetros, antes e depois).
- Execute apenas o que estiver na lista do manifesto.

PASSO 4 — ENTREGA
Não altere nada além do necessário para as funções e os endpoints. Ao final, liste as funções
criadas agrupadas por módulo (code, tipo e uma frase), informe a URL completa da API para
cadastrar no painel e diga como configurar a chave de execução no sistema legado. Depois, no painel, abra Funções e clique em "Importar do sistema
legado".`;

function stepModules(modules: PromptModule[]): string {
  if (modules.length === 0) {
    return `PASSO 1 — MÓDULOS
Nenhum módulo está cadastrado no painel ainda. Identifique os módulos funcionais do sistema
(áreas que um usuário reconhece pelo nome) e use esses nomes no campo "module". Cadastre os
mesmos nomes no painel antes de importar.`;
  }

  const lista = modules
    .map(
      (m) => `- ${m.name}${m.description ? `: ${m.description.replace(/\s+/g, ' ').trim()}` : ''}`,
    )
    .join('\n');

  return `PASSO 1 — MÓDULOS
Use exatamente estes nomes no campo "module" (são os módulos cadastrados no painel):
${lista}`;
}

/** Prompt exibido na tela, com os módulos já cadastrados para o campo "module" bater com o painel. */
function buildFunctionPrompt(modules: PromptModule[]): string {
  return [PROMPT_INTRO, PROMPT_CONTRATO, stepModules(modules), PROMPT_REGRAS].join('\n\n');
}

/**
 * Guia para quem implementa as funções no sistema legado: o que o painel espera
 * (manifesto, execução, autenticação, regras de segurança) e um prompt pronto
 * para uma IA de código gerar as funções. As funções não se cadastram à mão no
 * painel: nascem no sistema legado e entram pela importação do manifesto.
 */
@Component({
  selector: 'app-function-guide',
  imports: [RouterLink, PageHeaderComponent],
  templateUrl: './function-guide.component.html',
})
export class FunctionGuideComponent implements OnInit {
  private readonly componentService = inject(ComponentService);

  protected readonly manifesto = MANIFESTO_EXEMPLO;
  protected readonly pedido = PEDIDO_EXEMPLO;
  protected readonly respostaConsulta = RESPOSTA_CONSULTA_EXEMPLO;
  protected readonly respostaAlteracao = RESPOSTA_ALTERACAO_EXEMPLO;

  protected readonly prompt = signal(buildFunctionPrompt([]));
  protected readonly registeredModules = signal(0);
  protected readonly copied = signal(false);
  protected readonly copyError = signal<string | null>(null);

  ngOnInit(): void {
    // Sem a lista de módulos o prompt continua utilizável.
    this.componentService.listAll().subscribe((modules) => {
      this.registeredModules.set(modules.length);
      this.prompt.set(buildFunctionPrompt(modules));
    });
  }

  protected copyPrompt(): void {
    const falha = 'Não foi possível copiar automaticamente. Selecione o texto manualmente.';

    if (!navigator.clipboard) {
      this.copyError.set(falha);
      return;
    }

    navigator.clipboard
      .writeText(this.prompt())
      .then(() => {
        this.copyError.set(null);
        this.copied.set(true);
        setTimeout(() => this.copied.set(false), 2000);
      })
      .catch(() => this.copyError.set(falha));
  }
}
