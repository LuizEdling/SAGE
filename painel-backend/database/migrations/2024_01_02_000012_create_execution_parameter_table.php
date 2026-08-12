<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_parameter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('execution')->cascadeOnDelete();
            $table->foreignId('function_parameter_id')->constrained('function_parameter')->restrictOnDelete();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_parameter');
    }
};
