<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
    /**
     * Nama tabel yang terhubung dengan model.
     *
     * @var string
     */
    protected $table = 'password_reset_tokens';

    /**
     * Primary key dari tabel.
     *
     * @var string
     */
    protected $primaryKey = 'email'; // <-- Karena primary key-nya email

    /**
     * Tipe data dari primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Menandakan jika ID-nya auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false; // <-- Email tidak auto-increment

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array
     */
    protected $fillable = ['email', 'token', 'created_at'];

    /**
     * Model ini tidak menggunakan kolom 'updated_at'.
     */
    public const UPDATED_AT = null;
}
