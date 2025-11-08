<?php

namespace App\Http\Controllers\Api\MD;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminMahasiswaController extends Controller
{
    /**
     * List Mahasiswa + Pencarian + Pagination
     */
    public function index(Request $request)
    {
        $query = Mahasiswa::query();

        // Search (nama, nim, username, email)
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%$s%")
                    ->orWhere('nim', 'like', "%$s%")
                    ->orWhere('username', 'like', "%$s%")
                    ->orWhere('email', 'like', "%$s%");
            });
        }

        $mahasiswa = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $mahasiswa
        ]);
    }

    /**
     * Create Mahasiswa Baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'      => 'required|string|max:100',
            'nim'       => 'required|string|max:50|unique:mahasiswas,nim',
            'username'  => 'required|string|max:100|unique:mahasiswas,username',
            'email'     => 'required|email|max:100|unique:mahasiswas,email',
            'password'  => 'required|min:6',
            'no_telepon'=> 'required|max:20',
            'alamat'    => 'required',
            'angkatan'  => 'required',
            'prodi'     => 'required',
            'kelas'     => 'required',
            'foto'      => 'nullable|image|max:2048', // max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error','errors' => $validator->errors()], 422);
        }

        // Upload Foto
        $foto = 'default_mahasiswa.png';
        if ($request->hasFile('foto')) {
            $foto = $request->file('foto')->store('foto_mahasiswa', 'public');
        }

        $mhs = Mahasiswa::create([
            'nama'      => $request->nama,
            'nim'       => $request->nim,
            'username'  => $request->username,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'email_verified_at' => now(), // ✅ otomatis verified
            'no_telepon'=> $request->no_telepon,
            'foto'      => $foto,
            'alamat'    => $request->alamat,
            'angkatan'  => $request->angkatan,
            'prodi'     => $request->prodi,
            'kelas'     => $request->kelas,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Mahasiswa berhasil ditambahkan.',
            'data' => $mhs,
        ], 201);
    }

    /**
     * Update Mahasiswa
     */
    public function update(Request $request, $id)
    {
        $mhs = Mahasiswa::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama'      => 'required|string|max:100',
            'nim'       => 'required|string|max:50|unique:mahasiswas,nim,'.$mhs->id_mahasiswa.',id_mahasiswa',
            'username'  => 'required|string|max:100|unique:mahasiswas,username,'.$mhs->id_mahasiswa.',id_mahasiswa',
            'email'     => 'required|email|max:100|unique:mahasiswas,email,'.$mhs->id_mahasiswa.',id_mahasiswa',
            'password'  => 'nullable|min:6',
            'no_telepon'=> 'required|max:20',
            'alamat'    => 'required',
            'angkatan'  => 'required',
            'prodi'     => 'required',
            'kelas'     => 'required',
            'foto'      => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error','errors' => $validator->errors()], 422);
        }

        // Upload Foto Baru
        if ($request->hasFile('foto')) {
            if (!empty($mhs->foto) && $mhs->foto !== 'default_mahasiswa.png') {
                Storage::disk('public')->delete($mhs->foto);
            }

            $mhs->foto = $request->file('foto')->store('foto_mahasiswa', 'public');
        }


        $mhs->update([
            'nama'      => $request->nama,
            'nim'       => $request->nim,
            'username'  => $request->username,
            'email'     => $request->email,
            'email_verified_at' => now(), // ✅ selalu verified
            'no_telepon'=> $request->no_telepon,
            'alamat'    => $request->alamat,
            'angkatan'  => $request->angkatan,
            'prodi'     => $request->prodi,
            'kelas'     => $request->kelas,
        ]);

        if ($request->filled('password')) {
            $mhs->password = Hash::make($request->password);
            $mhs->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mahasiswa berhasil diperbarui.',
            'data' => $mhs,
        ]);
    }

    /**
     * Hapus Mahasiswa
     */
    public function destroy($id)
    {
        $mhs = Mahasiswa::findOrFail($id);

        if (!empty($mhs->foto) && $mhs->foto !== 'default_mahasiswa.png') {
            Storage::disk('public')->delete($mhs->foto);
        }

        $mhs->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mahasiswa berhasil dihapus.'
        ]);
    }

}
