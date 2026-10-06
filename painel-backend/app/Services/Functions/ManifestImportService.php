<?php

namespace App\Services\Functions;

use App\Enums\ParameterType;
use App\Models\AgentFunction;
use App\Models\Component;
use App\Services\ApprovalService;
use App\Services\Erp\ErpGateway;
use Illuminate\Support\Facades\DB;

/**
 * Importa as funções declaradas pelo sistema legado (manifesto do plugin).
 *
 * Função nova entra pendente e inativa; função que mudou no sistema legado
 * volta a pendente. Nunca aprova sozinha: quem decide se o Agente pode usar
 * uma função é o gestor (§4.4).
 */
class ManifestImportService
{
    public function __construct(
        private readonly ErpGateway $erp,
        private readonly ApprovalService $approval,
    ) {}

    /**
     * @return list<array{code:string, name:string, status:'criada'|'atualizada'|'inalterada'|'erro', message:?string}>
     */
    public function import(): array
    {
        $modulos = Component::all()->keyBy(fn (Component $c) => mb_strtolower($c->name));

        return array_map(function (array $item) use ($modulos) {
            // O manifesto é a única porta de entrada das funções: o que vier dele
            // precisa ser conferido aqui (o `code` vai na URL da chamada de execução).
            if (($erro = $this->validar($item)) !== null) {
                return $this->resultado($item, 'erro', $erro);
            }

            $modulo = $modulos->get(mb_strtolower($item['module'] ?? ''));

            if ($modulo === null) {
                return $this->resultado($item, 'erro', "Módulo \"{$item['module']}\" não está cadastrado no Painel.");
            }

            return DB::transaction(fn () => $this->importarFuncao($item, $modulo));
        }, $this->erp->manifest());
    }

    /** @return string|null a mensagem do primeiro problema, ou null se o item é válido */
    private function validar(array $item): ?string
    {
        if (! preg_match('/^[a-z0-9_.]{1,100}$/', (string) ($item['code'] ?? ''))) {
            return 'Código inválido: use só letras minúsculas, números, ponto e sublinhado (até 100 caracteres).';
        }

        foreach (['name', 'description', 'module'] as $campo) {
            if (trim((string) ($item[$campo] ?? '')) === '') {
                return "Campo obrigatório ausente: {$campo}.";
            }
        }

        if (mb_strlen((string) $item['name']) > 255) {
            return 'O nome da função deve ter até 255 caracteres.';
        }

        if (! in_array($item['operation_type'] ?? null, ['consulta', 'alteracao'], true)) {
            return 'Tipo de operação inválido: use consulta ou alteracao.';
        }

        $tiposValidos = array_column(ParameterType::cases(), 'value');

        foreach ($item['parameters'] ?? [] as $parametro) {
            if (trim((string) ($parametro['name'] ?? '')) === '' || ! in_array($parametro['type'] ?? null, $tiposValidos, true)) {
                return 'Parâmetro inválido: informe o nome e um tipo entre '.implode(', ', $tiposValidos).'.';
            }
        }

        return null;
    }

    private function importarFuncao(array $item, Component $modulo): array
    {
        $dados = [
            'name' => $item['name'],
            'description' => $item['description'],
            'operation_type' => $item['operation_type'],
            'component_id' => $modulo->id,
        ];

        $funcao = AgentFunction::where('code', $item['code'])->first();

        if ($funcao === null) {
            $funcao = AgentFunction::create(['code' => $item['code']] + $dados);
            $this->sincronizarParametros($funcao, $item['parameters'] ?? []);

            return $this->resultado($item, 'criada');
        }

        $funcao->fill($dados);
        $parametrosMudaram = $this->sincronizarParametros($funcao, $item['parameters'] ?? []);

        if (! $funcao->isDirty() && ! $parametrosMudaram) {
            return $this->resultado($item, 'inalterada');
        }

        // Mudou no sistema legado: precisa passar pela aprovação de novo.
        $this->approval->resetToPendingIfDecided($funcao);
        $funcao->save();

        return $this->resultado($item, 'atualizada');
    }

    /** @return bool se algum parâmetro foi criado, alterado ou removido */
    private function sincronizarParametros(AgentFunction $funcao, array $parametros): bool
    {
        $mudou = false;
        $nomes = [];

        foreach ($parametros as $parametro) {
            $nomes[] = $parametro['name'];

            $existente = $funcao->parameters()->firstOrNew(['name' => $parametro['name']]);
            $existente->fill([
                'type' => $parametro['type'],
                'required' => (bool) $parametro['required'],
                'description' => $parametro['description'] ?? null,
            ]);

            if (! $existente->exists || $existente->isDirty()) {
                $existente->save();
                $mudou = true;
            }
        }

        // Parâmetro que saiu do manifesto e nunca foi usado numa execução: remove.
        // O que já tem execução registrada fica, para não perder a auditoria.
        $removidos = $funcao->parameters()
            ->whereNotIn('name', $nomes)
            ->whereDoesntHave('executionParameters')
            ->delete();

        return $mudou || $removidos > 0;
    }

    private function resultado(array $item, string $status, ?string $message = null): array
    {
        return [
            'code' => $item['code'] ?? '',
            'name' => $item['name'] ?? '',
            'status' => $status,
            'message' => $message,
        ];
    }
}
