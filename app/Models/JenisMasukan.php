<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisMasukan extends Model
{
    use HasFactory;

    protected $table = 'jenis_masukans';
    protected $primaryKey = 'id_jenis_masukan';
    protected $fillable = ['jenis_masukan'];

    // Relasi: Satu Jenis Masukan punya banyak Pengaduan
    public function pengaduans(): HasMany
    {
        return $this->hasMany(Pengaduan::class, 'id_jenis_masukan', 'id_jenis_masukan');
    }
}
