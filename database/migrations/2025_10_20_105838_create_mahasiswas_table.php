<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mahasiswas', function (Blueprint $table) {
            $table->id('id_mahasiswa');
            $table->string('nama', 100);
            $table->string('nim', 50)->unique();
            $table->string('username', 100)->unique();
            $table->string('password');
            $table->string('email', 100)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('fcm_token')->nullable();
            $table->string('no_telepon', 20);
            $table->string('foto', 500)->default('default_mahasiswa.png');
            $table->text('alamat');
            $table->string('angkatan', 50);
            $table->string('prodi', 100);
            $table->string('kelas', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswas');
    }
};
