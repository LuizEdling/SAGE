<?php

namespace App\Console\Commands;

use App\Models\AgentFunction;
use App\Models\Knowledge;
use App\Services\Knowledge\IngestionDispatcherInterface;
use Illuminate\Console\Command;

/**
 * Vincula conhecimentos já importados às funções do sistema legado, a partir
 * de um mapa JSON: { "arquivo.md": { "inicio": "<início do texto>", "funcoes": ["codigo", ...] } }.
 *
 * O conhecimento é localizado pelo início do texto, porque o Painel não guarda
 * o nome do arquivo de origem. Os vínculos substituem os existentes e já vão
 * para os metadados do Qdrant.
 */
class LinkFunctionsCommand extends Command
{
    protected $signature = 'sage:vincular-funcoes {mapa : Caminho do JSON com o mapa conhecimento → funções}';

    protected $description = 'Vincula conhecimentos às funções do sistema legado a partir de um mapa JSON';

    public function handle(IngestionDispatcherInterface $ingestion): int
    {
        $mapa = json_decode((string) @file_get_contents($this->argument('mapa')), true);

        if (! is_array($mapa)) {
            $this->error('Não foi possível ler o mapa JSON.');

            return self::FAILURE;
        }

        $funcoes = AgentFunction::whereNotNull('code')->pluck('id', 'code');
        $falhas = 0;

        foreach ($mapa as $arquivo => $entrada) {
            $encontrados = Knowledge::where('content', 'like', addcslashes($entrada['inicio'], '%_\\').'%')->get();

            if ($encontrados->count() !== 1) {
                $this->warn("{$arquivo}: {$encontrados->count()} conhecimentos com esse início de texto — ignorado.");
                $falhas++;

                continue;
            }

            $desconhecidas = array_diff($entrada['funcoes'], $funcoes->keys()->all());

            if ($desconhecidas !== []) {
                $this->warn("{$arquivo}: funções não cadastradas: ".implode(', ', $desconhecidas).' — ignorado.');
                $falhas++;

                continue;
            }

            $knowledge = $encontrados->first();
            $knowledge->functions()->sync($funcoes->only($entrada['funcoes'])->values()->all());
            $ingestion->updateFunctions($knowledge);

            $this->line("#{$knowledge->id} {$arquivo} → ".implode(', ', $entrada['funcoes']));
        }

        $this->info(count($mapa) - $falhas.' vinculados, '.$falhas.' ignorados.');

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }
}
