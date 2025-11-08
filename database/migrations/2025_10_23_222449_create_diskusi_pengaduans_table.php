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
        Schema::create('diskusi_pengaduans', function (Blueprint $table) {
            $table->id('id_diskusi');
            $table->foreignId('id_pengaduan')->constrained('pengaduans', 'id_pengaduan')->cascadeOnDelete();
            $table->morphs('sender'); // Bisa Mahasiswa atau User
            $table->text('isi_pesan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diskusi_pengaduans');
    }
};
