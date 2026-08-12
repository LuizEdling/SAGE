<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('content');
            $table->foreignId('component_id')->constrained('component')->restrictOnDelete();
            $table->string('status')->default('pendente');
            $table->foreignId('approved_by')->nullable()->constrained('user')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->string('qdrant_point_id')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge');
    }
};
