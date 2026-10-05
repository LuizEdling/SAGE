<?php

namespace App\Services\Knowledge;

use App\Enums\KnowledgeType;
use App\Exceptions\InvalidImportArchiveException;
use App\Models\AgentFunction;
use App\Models\Component;
use App\Models\Knowledge;
use App\Repositories\ComponentRepository;
use App\Repositories\KnowledgeRepository;
use App\Support\FrontMatterParser;
use Illuminate\Http\UploadedFile;
use ZipArchive;

// Importa vários conhecimentos de uma vez a partir de um .zip de .md com
// front matter — pensado pro fluxo de "extrair conhecimento do código de um
// sistema legado" (Fase 3 do TCC), mas genérico: não sabe nada sobre nenhum
// sistema legado específico, só sobre o contrato de arquivo.
class KnowledgeImportService
{
    private const MAX_FILES = 300;

    private const MAX_FILE_BYTES = 512 * 1024;

    private const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    // Cada checagem de duplicidade é um round-trip HTTP pro agente Python —
    // acima desse teto, os itens seguintes vêm com `duplicates: 0` e a tela
    // avisa que a checagem foi parcial, em vez de deixar o import lento.
    private const MAX_DUPLICATE_CHECKS = 50;

    private const DEFAULT_COMPONENT_DESCRIPTION = 'Módulo criado automaticamente durante importação em massa de conhecimento.';

    public function __construct(
        private readonly KnowledgeRepository $knowledgeRepository,
        private readonly ComponentRepository $componentRepository,
        private readonly DuplicateCheckerInterface $duplicateChecker,
    ) {}

    /**
     * @return array{total:int, created:int, failed:int, items:list<array{
     *     file:string,
     *     status:'created'|'error',
     *     knowledge_id:?int,
     *     modulo:?string,
     *     message:?string,
     *     duplicates:int,
     * }>}
     */
    public function importFromZip(UploadedFile $archive): array
    {
        $zip = new ZipArchive;

        if ($zip->open($archive->getRealPath()) !== true) {
            throw new InvalidImportArchiveException('Não foi possível abrir o arquivo enviado — verifique se é um .zip válido.');
        }

        try {
            $entries = $this->collectMarkdownEntries($zip);

            $items = [];
            $created = 0;
            $duplicateChecks = 0;
            $componentCache = [];

            foreach ($entries as $zipIndex => $name) {
                $raw = $zip->getFromIndex($zipIndex, self::MAX_FILE_BYTES + 1);

                $result = $this->importOne(
                    file: basename($name),
                    raw: $raw === false ? '' : $raw,
                    componentCache: $componentCache,
                    duplicateChecks: $duplicateChecks,
                );

                $items[] = $result;

                if ($result['status'] === 'created') {
                    $created++;
                }
            }

            return [
                'total' => count($items),
                'created' => $created,
                'failed' => count($items) - $created,
                'items' => $items,
            ];
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> índice no zip => nome do arquivo, na ordem de leitura */
    private function collectMarkdownEntries(ZipArchive $zip): array
    {
        $entries = [];
        $totalBytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                continue;
            }

            $name = $stat['name'];

            if (str_ends_with($name, '/')) {
                continue;
            }

            $basename = basename($name);

            if (str_starts_with($basename, '.') || str_contains($name, '__MACOSX/')) {
                continue;
            }

            if (! str_ends_with(mb_strtolower($basename), '.md')) {
                continue;
            }

            if ($stat['size'] > self::MAX_FILE_BYTES) {
                throw new InvalidImportArchiveException(
                    "O arquivo \"{$basename}\" tem {$stat['size']} bytes, acima do limite de ".self::MAX_FILE_BYTES.' bytes por arquivo.'
                );
            }

            $totalBytes += $stat['size'];

            if ($totalBytes > self::MAX_TOTAL_BYTES) {
                throw new InvalidImportArchiveException('O conteúdo descomprimido do .zip excede o limite permitido.');
            }

            $entries[$i] = $name;

            if (count($entries) > self::MAX_FILES) {
                throw new InvalidImportArchiveException('O .zip contém mais de '.self::MAX_FILES.' arquivos .md — divida a importação em lotes menores.');
            }
        }

        if ($entries === []) {
            throw new InvalidImportArchiveException('Nenhum arquivo .md foi encontrado dentro do .zip.');
        }

        return $entries;
    }

    /**
     * @param  array<string, Component>  $componentCache  por referência, cache de módulos já resolvidos nesta execução
     * @return array{file:string, status:'created'|'error', knowledge_id:?int, modulo:?string, message:?string, duplicates:int}
     */
    private function importOne(string $file, string $raw, array &$componentCache, int &$duplicateChecks): array
    {
        try {
            ['meta' => $meta, 'body' => $body] = FrontMatterParser::parse($raw);

            $moduloNome = trim($meta['modulo'] ?? '');

            if ($moduloNome === '') {
                return $this->errorItem($file, null, 'Front matter sem o campo obrigatório "modulo".');
            }

            if ($body === '') {
                return $this->errorItem($file, $moduloNome, 'Arquivo sem conteúdo (corpo vazio após o front matter).');
            }

            $tipoValor = $meta['tipo'] ?? KnowledgeType::Base->value;
            $tipo = KnowledgeType::tryFrom($tipoValor);

            if ($tipo === null) {
                return $this->errorItem($file, $moduloNome, "Valor inválido para \"tipo\": \"{$tipoValor}\" (use \"base\" ou \"temporario\").");
            }

            $validUntil = null;

            if ($tipo === KnowledgeType::Temporario) {
                $validUntilRaw = trim($meta['valid_until'] ?? '');

                if ($validUntilRaw === '') {
                    return $this->errorItem($file, $moduloNome, 'tipo "temporario" exige o campo "valid_until".');
                }

                try {
                    $validUntil = new \DateTimeImmutable($validUntilRaw);
                } catch (\Exception) {
                    return $this->errorItem($file, $moduloNome, "Data inválida em \"valid_until\": \"{$validUntilRaw}\".");
                }
            }

            $component = $this->resolveComponent($moduloNome, $meta, $componentCache);

            $duplicates = 0;

            if ($duplicateChecks < self::MAX_DUPLICATE_CHECKS) {
                $duplicates = $this->duplicateChecker->findDuplicates($component->id, $body)->count();
                $duplicateChecks++;
            }

            $knowledge = $this->knowledgeRepository->create([
                'type' => $tipo,
                'content' => $body,
                'component_id' => $component->id,
                'valid_until' => $validUntil,
            ]);

            $aviso = $this->vincularFuncoes($knowledge, $meta['funcoes'] ?? '');

            return [
                'file' => $file,
                'status' => 'created',
                'knowledge_id' => $knowledge->id,
                'modulo' => $component->name,
                'message' => $aviso,
                'duplicates' => $duplicates,
            ];
        } catch (InvalidImportArchiveException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->errorItem($file, null, 'Erro inesperado ao processar o arquivo: '.$e->getMessage());
        }
    }

    /**
     * Vincula as funções declaradas em `funcoes: codigo1, codigo2`. O
     * conhecimento entra pendente: o gestor vê o vínculo antes de aprovar.
     * Código desconhecido não derruba o arquivo — só vira aviso.
     */
    private function vincularFuncoes(Knowledge $knowledge, string $declaradas): ?string
    {
        $codigos = array_values(array_filter(array_map('trim', explode(',', $declaradas))));

        if ($codigos === []) {
            return null;
        }

        $encontradas = AgentFunction::whereIn('code', $codigos)->pluck('id', 'code');
        $knowledge->functions()->sync($encontradas->values()->all());

        $desconhecidas = array_diff($codigos, $encontradas->keys()->all());

        return $desconhecidas === []
            ? null
            : 'Funções não cadastradas, ignoradas: '.implode(', ', $desconhecidas).'.';
    }

    /** @param array<string, Component> $componentCache */
    private function resolveComponent(string $name, array $meta, array &$componentCache): Component
    {
        $cacheKey = mb_strtolower($name);

        if (isset($componentCache[$cacheKey])) {
            return $componentCache[$cacheKey];
        }

        $existing = Component::whereRaw('LOWER(name) = ?', [$cacheKey])->first();

        if ($existing) {
            return $componentCache[$cacheKey] = $existing;
        }

        $created = $this->componentRepository->create([
            'name' => $name,
            'description' => trim($meta['descricao_modulo'] ?? '') ?: self::DEFAULT_COMPONENT_DESCRIPTION,
            'route' => trim($meta['rota_modulo'] ?? '') ?: null,
        ]);

        return $componentCache[$cacheKey] = $created;
    }

    /** @return array{file:string, status:'error', knowledge_id:null, modulo:?string, message:string, duplicates:int} */
    private function errorItem(string $file, ?string $modulo, string $message): array
    {
        return [
            'file' => $file,
            'status' => 'error',
            'knowledge_id' => null,
            'modulo' => $modulo,
            'message' => $message,
            'duplicates' => 0,
        ];
    }
}
