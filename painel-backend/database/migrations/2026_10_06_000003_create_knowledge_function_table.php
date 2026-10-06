<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo conhecimento ↔ função (§4.3: as informações das funções "são
 * utilizadas nos metadados da base de conhecimento"). Os ids das funções
 * vinculadas vão para o payload de cada trecho no Qdrant, e o Agente só
 * considera as funções ligadas ao conhecimento que de fato recuperou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_function', function (Blueprint $table) {
            $table->foreignId('knowledge_id')->constrained('knowledge')->cascadeOnDelete();
            $table->foreignId('function_id')->constrained('function')->cascadeOnDelete();

            $table->primary(['knowledge_id', 'function_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_function');
    }
};
