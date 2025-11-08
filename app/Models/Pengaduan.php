<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Pengaduan extends Model
{
    use HasFactory;

    protected $table = 'pengaduans';
    protected $primaryKey = 'id_pengaduan';
    protected $fillable = [
        'judul_pengaduan',
        'lokasi',
        'isi_laporan',
        'status',
        'id_mahasiswa',
        'id_jenis_masukan',
        'id_jenis_pengaduan',
        'id_pihak_terkait',
        'id_user_penanggap_terakhir', // Ditambahkan agar bisa di-update
    ];

    // Relasi: Satu Pengaduan milik satu Mahasiswa
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'id_mahasiswa', 'id_mahasiswa');
    }

    // Relasi: Satu Pengaduan milik satu Jenis Masukan
    public function jenisMasukan(): BelongsTo
    {
        return $this->belongsTo(JenisMasukan::class, 'id_jenis_masukan', 'id_jenis_masukan');
    }

    // Relasi: Satu Pengaduan milik satu Jenis Pengaduan (jika tidak null)
    public function jenisPengaduan(): BelongsTo
    {
        return $this->belongsTo(JenisPengaduan::class, 'id_jenis_pengaduan', 'id_jenis_pengaduan');
    }

    // Relasi: Satu Pengaduan milik satu Pihak Terkait (jika tidak null)
    public function pihakTerkait(): BelongsTo
    {
        return $this->belongsTo(PihakTerkait::class, 'id_pihak_terkait', 'id_pihak_terkait');
    }

    // Relasi: Satu Pengaduan ditanggapi terakhir oleh satu User (jika tidak null)
    public function penanggapTerakhir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user_penanggap_terakhir', 'id_user');
    }

    // Relasi: Satu Pengaduan punya banyak Tanggapan Resmi
    public function tanggapans(): HasMany
    {
        return $this->hasMany(Tanggapan::class, 'id_pengaduan', 'id_pengaduan');
    }

    // Relasi: Satu Pengaduan punya banyak Pesan Diskusi
    public function diskusiPengaduans(): HasMany
    {
        return $this->hasMany(DiskusiPengaduan::class, 'id_pengaduan', 'id_pengaduan');
    }

    // Relasi: Satu Pengaduan bisa punya banyak Lampiran (Polymorphic)
    public function lampirans(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }

    // Relasi: Satu Pengaduan bisa punya banyak Notifikasi (Polymorphic)
     public function notifikasis(): MorphMany
     {
         return $this->morphMany(Notifikasi::class, 'notifiable'); // Asumsi Notifikasi bisa terkait Pengaduan
     }
}
