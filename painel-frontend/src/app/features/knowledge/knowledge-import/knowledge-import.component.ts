import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { KnowledgeService } from '../knowledge.service';
import { ComponentService } from '../../components/component.service';
import { KnowledgeImportResult } from '../../../core/models/knowledge.model';
import { PageHeaderComponent } from '../../../shared/components/page-header/page-header.component';

interface PromptModule {
  name: string;
  description: string;
}

const PROMPT_INTRO = `Você está com acesso ao repositório de código-fonte de um sistema legado. Sua tarefa é
extrair conhecimento operacional desse código e gerar arquivos Markdown que serão
importados na base de conhecimento do S.A.G.E., um assistente de suporte técnico.

CONTEXTO
O S.A.G.E. atende usuários finais desse sistema de duas formas: resolve problemas de
suporte (por que algo não funciona, o que fazer) e ensina o sistema (o que é cada tela e
cada conceito, para que serve, como usar), inclusive no treinamento de funcionários
novos. Ele não lê código: ele lê os arquivos que você vai gerar. Cada arquivo deve
descrever, em português claro e voltado a quem usa ou atende o sistema, um comportamento
real — nunca a implementação.

Existem dois tipos de conhecimento, e cada módulo deve ter os dois:
- DIAGNÓSTICO: um problema que o usuário relata, sua causa e a solução;
- GUIA: uma explicação de como o sistema funciona — o que uma tela mostra, o que um
  conceito significa, como um processo acontece, como executar uma tarefa.`;

const STEP1_FREE = `PASSO 1 — MAPEAR OS MÓDULOS
Percorra o repositório e identifique os módulos funcionais do sistema, ou seja, as áreas
que um usuário reconheceria pelo nome (ex: "Cadastro de Clientes", "Faturamento",
"Controle de Estoque"). Use como pistas a estrutura de diretórios, as rotas e
controllers, os menus da interface, os nomes de entidades/tabelas e os textos e traduções
exibidos ao usuário. Ignore o que é puramente infraestrutura: build, CI, testes,
migrations, dependências de terceiros.`;

function step1Registered(modules: PromptModule[]): string {
  const list = modules
    .map((m) => `- "${m.name}" — ${m.description.replace(/\s+/g, ' ').trim()}`)
    .join('\n');

  return `PASSO 1 — USAR OS MÓDULOS JÁ CADASTRADOS NO PAINEL
O painel já tem os módulos abaixo cadastrados. Gere conhecimento SOMENTE para eles e
escreva o campo "modulo" com o nome exatamente como aparece entre aspas (sem as aspas),
inclusive acentos, vírgulas e maiúsculas — um nome diferente cria um módulo novo no
painel e separa o conhecimento do lugar certo. Use a descrição de cada módulo para saber
o que pertence a ele e localize no repositório o código correspondente (rotas,
controllers, menus, entidades/tabelas, textos e traduções). Ignore o que é puramente
infraestrutura: build, CI, testes, migrations, dependências de terceiros.

${list}

Se encontrar algo relevante para o suporte que não se encaixe em nenhum desses módulos,
não gere arquivo: cite no relatório final (PASSO 4) como sugestão de novo módulo.`;
}

const PROMPT_BODY = `PASSO 2 — EXTRAIR O CONHECIMENTO DE CADA MÓDULO
Trabalhe um módulo por vez e comece pelos GUIAS (2A), que dão a visão geral do módulo; só
depois gere os DIAGNÓSTICOS (2B). Todo módulo deve ficar com pelo menos 1 guia de visão
geral, 1 guia por tela principal e 1 guia por conceito central antes de você aprofundar
nos diagnósticos de qualquer módulo — nenhum módulo pode ficar sem cobertura básica.

2A. GUIAS (para ensinar e explicar). Um guia por assunto:
- visão geral do módulo: o que é, para que serve, quem usa, onde fica no menu e como se
  relaciona com os outros módulos;
- cada tela principal (listas e formulários): onde fica, o que mostra (colunas, abas,
  filtros, botões, campos importantes) e para que serve;
- conceitos e termos do módulo: o que cada status, tipo, perfil ou campo relevante
  significa, quando é atribuído ou usado e o que muda a partir dele — quando houver uma
  família de valores (ex.: todos os status), faça um guia da família e, para os valores
  menos óbvios, um guia próprio;
- fluxos: como um processo acontece do início ao fim, na ordem em que o usuário o vive;
- tarefas comuns, passo a passo: como o usuário realiza a operação mais frequente do
  módulo, usando os nomes de menu, tela e botão como aparecem na interface.

2B. DIAGNÓSTICOS (para resolver problemas). Procure e descreva — um conhecimento por regra:
- regras de negócio e validações que impedem uma ação, explicando o que dispara a
  mensagem de erro e o que o usuário precisa fazer para destravar;
- pré-condições, permissões e perfis exigidos para executar uma operação;
- mensagens de erro visíveis ao usuário, com a causa real e a solução;
- efeitos colaterais relevantes: o que muda de status, o que é gerado, o que fica
  bloqueado depois da operação;
- rotinas automáticas e agendadas, que explicam "por que ainda não apareceu";
- parâmetros de configuração que mudam o comportamento do sistema.

Em guias e em diagnósticos, ignore detalhes de implementação sem efeito visível para o
usuário, código morto, refatorações e comentários TODO.

PASSO 3 — ESCREVER OS ARQUIVOS
Crie a pasta "conhecimento-sage" na raiz do repositório e grave um arquivo .md por
conhecimento, nomeado como <modulo-em-kebab-case>-<assunto-em-kebab-case>.md para
diagnósticos (ex: faturamento-nota-sem-inscricao-estadual.md) e como
<modulo-em-kebab-case>-guia-<assunto-em-kebab-case>.md para guias
(ex: faturamento-guia-tela-lista-de-notas.md).

Cada arquivo DEVE seguir exatamente este formato: um front matter entre duas linhas
compostas apenas por três hífens, seguido do texto do conhecimento.

---
modulo: Faturamento
descricao_modulo: Emissão de notas fiscais e faturas a partir dos pedidos aprovados.
rota_modulo: Vendas > Faturamento
tipo: base
---

<texto do conhecimento>

Regras do front matter:
- modulo (obrigatório): nome do módulo, escrito exatamente igual em todos os arquivos do
  mesmo módulo — é ele que agrupa os conhecimentos.
- descricao_modulo (opcional): uma frase; usada apenas se o módulo ainda não existir no
  painel. Para módulo já cadastrado, pode omitir.
- rota_modulo (opcional): caminho de menu da tela desse módulo, como o usuário vê
  (ex: "Vendas > Faturamento"). Usada apenas se o módulo ainda não existir no painel.
- tipo (opcional): "base" (padrão) ou "temporario". Conhecimento extraído de código é
  sempre "base".
- Use somente pares "chave: valor" em uma única linha. Nada de listas, indentação,
  aninhamento ou valores multilinha.

Regras do corpo do arquivo (valem para os dois tipos):
- Português claro, sem jargão técnico: não cite nomes de classes, funções, arquivos,
  tabelas ou colunas.
- Um único conhecimento por arquivo, autocontido — quem for ler não tem nenhum outro
  contexto. Escreva o nome do módulo e o nome da tela ou do conceito por extenso no texto
  (ex: "Na tela Chamados..."), nunca "esta tela" ou "o módulo".
- Diagnóstico: entre 3 e 15 linhas, estruturadas como: sintoma percebido pelo usuário,
  causa, e o que fazer para resolver.
- Guia: entre 5 e 25 linhas, no máximo 2.000 caracteres, sem linhas em branco no corpo (o
  guia deve ficar em um único fragmento). A primeira linha é "Assunto:" seguida do que está
  sendo explicado (ex: "Assunto: tela Chamados, a lista de chamados."). Os primeiros 600
  caracteres são os que mais pesam na busca: neles devem estar o nome do assunto, o que é e
  para que serve. Depois, uma linha por rótulo, usando só os que se aplicam: "O que é:" ou
  "O que mostra:", "Para que serve:", "Como funciona:", "Onde encontrar:", "Passo a passo:"
  (ações do usuário numeradas) e "Atenção:". Escreva como um instrutor que explica a um
  funcionário novo, com os termos exatamente como a interface os exibe.
- Ao indicar onde fazer algo, use o caminho de menu e os nomes de tela e de campo como o
  usuário vê (ex: "Administração > Usuários, aba Autorizações"), nunca URLs internas.
- Cite entre aspas as mensagens exatamente como o usuário as vê na interface. Se o
  sistema tiver arquivos de tradução, use o texto no idioma em que o sistema é usado
  (procure a tradução da mensagem), não o texto original do código.
- Não invente comportamento: se o código não deixar claro, não escreva o arquivo. Num guia,
  só descreva colunas, abas, botões, status e campos que existam no código e nas
  traduções do sistema.
- Não use linhas com três hífens dentro do corpo.
- Não inclua dados sensíveis: credenciais, endereços de servidor, nomes de clientes reais.

PASSO 4 — CONFERIR E ENTREGAR
Cada .zip enviado ao painel aceita no máximo 300 arquivos .md, de até 512 KB cada. Se
gerar mais de 300 arquivos, divida-os em subpastas dentro de "conhecimento-sage"
(lote-01, lote-02, ...) com no máximo 300 arquivos cada — cada subpasta vira um .zip.

Ao final, liste os arquivos gerados agrupados por módulo, informando quantos ficaram em
cada um (separando guias e diagnósticos) e quais módulos ficaram sem guia de visão geral,
sem guia de alguma tela principal ou sem diagnósticos, e as sugestões de novos módulos,
se houver. Não altere nenhum outro arquivo do
repositório. A pasta "conhecimento-sage" deve ficar pronta para ser compactada em .zip e
enviada na tela de importação do painel.`;

/**
 * Monta o prompt exibido na tela. Com módulos já cadastrados, o PASSO 1 lista os nomes
 * exatos — sem isso a IA inventa nomes a partir do código e o import cria módulos
 * duplicados, sem descrição nem imagens.
 */
function buildImportPrompt(modules: PromptModule[]): string {
  const step1 = modules.length > 0 ? step1Registered(modules) : STEP1_FREE;

  return [PROMPT_INTRO, step1, PROMPT_BODY].join('\n\n');
}

@Component({
  selector: 'app-knowledge-import',
  imports: [RouterLink, PageHeaderComponent],
  templateUrl: './knowledge-import.component.html',
  // Reforça só o `.tip-box` desta tela — a classe é compartilhada com o
  // cadastro de conhecimento, e lá o aviso é só um lembrete, não um passo a
  // passo obrigatório para o processo funcionar.
  styles: [
    `
      .tip-box {
        background: var(--sage-warning-bg);
        border-left-width: 4px;
        box-shadow: 0 0 0 1px color-mix(in srgb, var(--sage-action) 18%, transparent);
      }
    `,
  ],
})
export class KnowledgeImportComponent implements OnInit {
  private readonly service = inject(KnowledgeService);
  private readonly componentService = inject(ComponentService);

  protected readonly examplePrompt = signal(buildImportPrompt([]));
  protected readonly registeredModules = signal(0);

  protected readonly selectedFile = signal<File | null>(null);
  protected readonly uploading = signal(false);
  protected readonly result = signal<KnowledgeImportResult | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly copied = signal(false);

  ngOnInit(): void {
    // Sem a lista de módulos o prompt continua utilizável (modo de mapeamento livre).
    this.componentService.listAll().subscribe((modules) => {
      this.registeredModules.set(modules.length);
      this.examplePrompt.set(buildImportPrompt(modules));
    });
  }

  protected onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile.set(input.files?.[0] ?? null);
    this.result.set(null);
    this.errorMessage.set(null);
  }

  protected copyPrompt(): void {
    if (!navigator.clipboard) {
      this.errorMessage.set('Não foi possível copiar automaticamente. Selecione o texto manualmente.');
      return;
    }

    navigator.clipboard
      .writeText(this.examplePrompt())
      .then(() => {
        this.copied.set(true);
        setTimeout(() => this.copied.set(false), 2000);
      })
      .catch(() => this.errorMessage.set('Não foi possível copiar automaticamente. Selecione o texto manualmente.'));
  }

  protected submit(): void {
    const file = this.selectedFile();

    if (!file || this.uploading()) {
      return;
    }

    this.uploading.set(true);
    this.errorMessage.set(null);
    this.result.set(null);

    this.service.importZip(file).subscribe({
      next: (result) => {
        this.result.set(result);
        this.uploading.set(false);
      },
      error: (err) => {
        this.errorMessage.set(err?.error?.error?.message ?? 'Não foi possível importar o arquivo. Tente novamente.');
        this.uploading.set(false);
      },
    });
  }
}
