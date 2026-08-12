<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_id')->constrained('knowledge')->cascadeOnDelete();
            $table->string('url');
            $table->string('caption')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_image');
    }
};
