<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('message')->restrictOnDelete();
            $table->foreignId('function_id')->constrained('function')->restrictOnDelete();
            $table->foreignId('confirmation_message_id')->nullable()
                ->constrained('message')->nullOnDelete();
            $table->string('status')->default('pendente');
            $table->dateTime('authorized_at')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution');
    }
};
