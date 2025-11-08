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
        Schema::create('tanggapans', function (Blueprint $table) {
            $table->id('id_tanggapan');
            $table->foreignId('id_pengaduan')->constrained('pengaduans', 'id_pengaduan')->cascadeOnDelete();
            $table->foreignId('id_user')->constrained('users', 'id_user')->cascadeOnDelete();
            $table->text('isi_tanggapan');

            // Status BARU yang ditetapkan oleh tanggapan ini
            $table->enum('status_tanggapan', ['Diproses', 'Selesai', 'Ditolak']);
            $table->timestamps(); // created_at (tanggal tanggapan) & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('masterdata_tanggapans');
    }
};
