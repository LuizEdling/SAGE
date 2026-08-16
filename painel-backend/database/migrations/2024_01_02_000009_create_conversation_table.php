<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_access_key_id')->constrained('chat_access_key')->restrictOnDelete();
            $table->string('status')->default('aberta');
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['chat_access_key_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation');
    }
};
