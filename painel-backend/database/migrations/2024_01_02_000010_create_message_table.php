<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversation')->restrictOnDelete();
            $table->text('content');
            $table->string('author');
            $table->text('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message');
    }
};
