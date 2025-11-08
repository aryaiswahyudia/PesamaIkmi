<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class Iklan extends Model
{
    use HasFactory;
    protected $primaryKey = 'id_iklan';
    protected $fillable = ['judul', 'foto_iklan', 'deskripsi', 'is_aktif'];

    // Accessor untuk URL iklan
    public function getFotoIklanAttribute($value)
    {
        if (!$value || Str::startsWith($value, 'http')) {
            return $value;
        }
        return Storage::disk('public')->url($value);
    }
}
