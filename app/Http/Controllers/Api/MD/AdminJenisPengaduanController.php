<?php

namespace App\Http\Controllers\Api\MD;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JenisPengaduan;
use Illuminate\Support\Facades\Validator;

class AdminJenisPengaduanController extends Controller
{
    /**
     * ✅ LIST + SEARCH + PAGINATION
     */
    public function index(Request $request)
    {
        $query = JenisPengaduan::query();

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('jenis_pengaduan', 'LIKE', "%$s%");
        }

        $data = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }


    /**
     * ✅ CREATE
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_pengaduan' => 'required|string|max:100|unique:jenis_pengaduans,jenis_pengaduan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $jp = JenisPengaduan::create([
            'jenis_pengaduan' => $request->jenis_pengaduan,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis pengaduan berhasil ditambahkan.',
            'data' => $jp
        ]);
    }


    /**
     * ✅ UPDATE
     */
    public function update(Request $request, $id)
    {
        $jp = JenisPengaduan::find($id);

        if (!$jp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jenis pengaduan tidak ditemukan.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'jenis_pengaduan' => "required|string|max:100|unique:jenis_pengaduans,jenis_pengaduan,$id,id_jenis_pengaduan",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $jp->update([
            'jenis_pengaduan' => $request->jenis_pengaduan,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis pengaduan berhasil diperbarui.',
            'data' => $jp
        ]);
    }


    /**
     * ✅ DELETE
     */
    public function destroy($id)
    {
        $jp = JenisPengaduan::find($id);

        if (!$jp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Jenis pengaduan tidak ditemukan.'
            ], 404);
        }

        $jp->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis pengaduan berhasil dihapus.'
        ]);
    }
}
