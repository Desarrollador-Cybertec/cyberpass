<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Discrimina tokens de sesion humana de tokens de integracion.
            //
            // El default rellena las filas existentes como 'session', asi que
            // todos los tokens vivos siguen comportandose igual que hoy.
            //
            // Se usa una columna y no el 'name' (etiqueta mutable que pone el
            // usuario) ni las abilities: acoplar la autenticacion a la
            // autorizacion haria que un bug en las abilities por defecto
            // convirtiera un token de sesion en inmortal.
            $table->string('type', 32)->default('session')->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
