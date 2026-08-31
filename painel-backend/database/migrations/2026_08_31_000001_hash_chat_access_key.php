<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_access_key', function (Blueprint $table) {
            $table->string('key_hash', 64)->nullable()->after('id');
            $table->foreignId('created_by_user_id')->nullable()->after('key_hash')
                ->constrained('user')->nullOnDelete();
        });

        // Chaves antigas ficavam em texto puro. Converte cada uma pro hash sem
        // invalidá-las: quem já tem o valor original continua conseguindo usar.
        foreach (DB::table('chat_access_key')->select('id', 'access_key')->get() as $row) {
            DB::table('chat_access_key')
                ->where('id', $row->id)
                ->update(['key_hash' => hash('sha256', $row->access_key)]);
        }

        Schema::table('chat_access_key', function (Blueprint $table) {
            // O índice sai antes da coluna: o MySQL faria isso sozinho, mas o
            // SQLite (banco dos testes) recusa remover coluna indexada.
            $table->dropUnique(['access_key']);
            $table->dropColumn('access_key');
        });

        Schema::table('chat_access_key', function (Blueprint $table) {
            $table->string('key_hash', 64)->nullable(false)->change();
            $table->unique('key_hash');
        });
    }

    public function down(): void
    {
        // O valor em claro não é recuperável a partir do hash: o rollback devolve
        // a coluna, mas as chaves existentes precisam ser regeradas.
        Schema::table('chat_access_key', function (Blueprint $table) {
            $table->string('access_key')->nullable()->after('id');
            $table->dropUnique(['key_hash']);
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('key_hash');
        });
    }
};
