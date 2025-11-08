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
        Schema::create('pengaduans', function (Blueprint $table) {
            $table->id('id_pengaduan');
            $table->string('judul_pengaduan', 255);
            $table->string('lokasi', 155)->nullable();
            $table->text('isi_laporan');
            $table->enum('status', ['Belum Ditanggapi', 'Diproses', 'Selesai', 'Ditolak'])
                ->default('Belum Ditanggapi');

            // Relasi ke pelapor dan kategori
            $table->foreignId('id_mahasiswa')->constrained('mahasiswas', 'id_mahasiswa')->cascadeOnDelete();
            $table->foreignId('id_jenis_masukan')->constrained('jenis_masukans', 'id_jenis_masukan')->cascadeOnDelete();
            $table->foreignId('id_jenis_pengaduan')->nullable()->constrained('jenis_pengaduans', 'id_jenis_pengaduan')->nullOnDelete();
            $table->foreignId('id_pihak_terkait')->nullable()->constrained('pihak_terkait', 'id_pihak_terkait')->nullOnDelete();

            // ID User yang memberikan tanggapan TERAKHIR (opsional, untuk info cepat)
            $table->foreignId('id_user_penanggap_terakhir')->nullable()->constrained('users', 'id_user')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaduans');
    }
};
