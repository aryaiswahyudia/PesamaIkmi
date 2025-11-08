<?php

namespace App\Http\Controllers\Api\MD;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PihakTerkait;
use App\Models\JenisPengaduan;
use Illuminate\Support\Facades\Validator;

class AdminPihakTerkaitController extends Controller
{
    /**
     * GET ALL + SEARCH + PAGINATION
     */
    public function index(Request $request)
    {
        $query = PihakTerkait::with('jenisPengaduan');

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('nama_pihak', 'like', "%$s%");
        }

        $data = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * STORE
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_pihak' => 'required|string|max:100',
            'id_jenis_pengaduan' => 'nullable|exists:jenis_pengaduans,id_jenis_pengaduan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'VALIDATION_ERROR',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = PihakTerkait::create([
            'nama_pihak' => $request->nama_pihak,
            'id_jenis_pengaduan' => $request->id_jenis_pengaduan,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Pihak terkait berhasil ditambahkan',
            'data' => $data
        ]);
    }

    /**
     * UPDATE
     */
    public function update(Request $request, $id)
    {
        $pihak = PihakTerkait::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama_pihak' => 'required|string|max:100',
            'id_jenis_pengaduan' => 'nullable|exists:jenis_pengaduans,id_jenis_pengaduan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'VALIDATION_ERROR',
                'errors' => $validator->errors()
            ], 422);
        }

        $pihak->update([
            'nama_pihak' => $request->nama_pihak,
            'id_jenis_pengaduan' => $request->id_jenis_pengaduan,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Data berhasil diperbarui',
            'data' => $pihak
        ]);
    }

    /**
     * DELETE
     */
    public function destroy($id)
    {
        $pihak = PihakTerkait::findOrFail($id);
        $pihak->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data berhasil dihapus'
        ]);
    }
}
