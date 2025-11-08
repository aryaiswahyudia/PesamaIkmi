<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute; // Untuk Accessor URL
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage; // Untuk Accessor URL

class Lampiran extends Model
{
    use HasFactory;

    protected $table = 'lampirans';
    protected $primaryKey = 'id_lampiran';
    protected $fillable = [
        'lampiranable_id',
        'lampiranable_type',
        'nama_file',
        'path_file',
        'tipe_file',
        'ukuran_file',
    ];

    // Relasi: Lampiran ini milik Pengaduan/Tanggapan/Diskusi (Polymorphic)
    public function lampiranable(): MorphTo
    {
        return $this->morphTo();
    }

    // Accessor: Membuat URL publik untuk file
    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk('public')->url($this->path_file),
        );
    }

    // Otomatis tambahkan 'file_url' ke JSON response
    protected $appends = ['file_url'];

    // Sembunyikan path asli dari JSON response (opsional)
    protected $hidden = ['path_file'];
}
