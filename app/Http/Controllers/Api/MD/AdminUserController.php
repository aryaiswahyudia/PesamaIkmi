<?php

namespace App\Http\Controllers\Api\MD;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class AdminUserController extends Controller
{
    // ============================================================
    // INDEX + PAGINATION
    // ============================================================
    public function index(Request $request)
    {
        $query = User::query();

        // Search by nama, username, email
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', "%$s%")
                  ->orWhere('username', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%");
            });
        }

        $users = $query
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    // ============================================================
    // CREATE USER
    // ============================================================
    public function store(Request $request)
    {
        $request->validate([
            'nama'        => 'required|string|max:255',
            'username'    => 'required|string|max:100|unique:users,username',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|min:6',
            'no_telepon'  => 'required|max:20',
            'jabatan'     => ['required', Rule::in(['administrator', 'operator'])],
            'foto_profile' => 'nullable|image|max:3000',
        ]);

        $fotoName = "default_user.png";

        if ($request->hasFile('foto_profile')) {
            $fotoName = time().'_'.$request->foto_profile->getClientOriginalName();
            $request->foto_profile->storeAs('public/users', $fotoName);
        }

        $user = User::create([
            'nama'              => $request->nama,
            'username'          => $request->username,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'no_telepon'        => $request->no_telepon,
            'jabatan'           => $request->jabatan,
            'foto_profile'      => $fotoName,
            'email_verified_at' => now(), // ✅ Langsung dianggap terverifikasi
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dibuat.',
            'data' => $user
        ]);
    }

    // ============================================================
    // UPDATE USER
    // ============================================================
    public function update(Request $request, $id_user)
    {
        $user = User::findOrFail($id_user);

        $request->validate([
            'nama'        => 'required|string|max:255',
            'username'    => ['required', 'max:100', Rule::unique('users')->ignore($user->id_user, 'id_user')],
            'email'       => ['required', 'email', Rule::unique('users')->ignore($user->id_user, 'id_user')],
            'password'    => 'nullable|min:6',
            'no_telepon'  => 'required|max:20',
            'jabatan'     => ['required', Rule::in(['administrator', 'operator'])],
            'foto_profile' => 'nullable|image|max:3000',
        ]);

        // Upload foto
        if ($request->hasFile('foto_profile')) {
            // delete foto lama
            if ($user->foto_profile !== 'default_user.png') {
                Storage::delete('public/users/'.$user->foto_profile);
            }

            $fotoName = time().'_'.$request->foto_profile->getClientOriginalName();
            $request->foto_profile->storeAs('public/users', $fotoName);
            $user->foto_profile = $fotoName;
        }

        $user->nama = $request->nama;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->no_telepon = $request->no_telepon;
        $user->jabatan = $request->jabatan;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // ✅ Email langsung dianggap terverifikasi setelah update
        $user->email_verified_at = now();

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil diperbarui.',
            'data' => $user
        ]);
    }

    // ============================================================
    // DELETE USER
    // ============================================================
    public function destroy($id_user)
    {
        $user = User::findOrFail($id_user);

        if ($user->foto_profile !== 'default_user.png') {
            Storage::delete('public/users/'.$user->foto_profile);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dihapus.'
        ]);
    }
}
