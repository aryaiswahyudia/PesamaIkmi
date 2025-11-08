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
        Schema::create('jenis_masukans', function (Blueprint $table) {
            $table->id('id_jenis_masukan');
            $table->string('jenis_masukan', 100);
            $table->timestamps();
        });

        Schema::create('jenis_pengaduans', function (Blueprint $table) {
            $table->id('id_jenis_pengaduan');
            $table->string('jenis_pengaduan', 100);
            $table->timestamps();
        });

        Schema::create('pihak_terkait', function (Blueprint $table) {
            $table->id('id_pihak_terkait');
            $table->string('nama_pihak', 100);
            $table->unsignedBigInteger('id_jenis_pengaduan')->nullable();
            $table->timestamps();

            $table->foreign('id_jenis_pengaduan')
                ->references('id_jenis_pengaduan')
                ->on('jenis_pengaduans')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jenis_masukans');
        Schema::dropIfExists('jenis_pengaduans');
        Schema::dropIfExists('pihak_terkait');
    }
};
