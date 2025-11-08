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
        Schema::create('notifikasis', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->morphs('notifiable');
            $table->foreignId('id_pengaduan')->nullable()->constrained('pengaduans', 'id_pengaduan')->cascadeOnDelete();
            $table->string('tipe_notifikasi');
            $table->string('judul_notifikasi');
            $table->text('pesan_notifikasi');
            $table->boolean('dibaca')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasis');
    }
};
