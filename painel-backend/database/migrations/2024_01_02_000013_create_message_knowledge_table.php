<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_knowledge', function (Blueprint $table) {
            $table->foreignId('message_id')->constrained('message')->cascadeOnDelete();
            $table->foreignId('knowledge_id')->constrained('knowledge')->cascadeOnDelete();

            $table->primary(['message_id', 'knowledge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_knowledge');
    }
};
