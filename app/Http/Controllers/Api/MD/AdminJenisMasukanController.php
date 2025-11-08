<?php

namespace App\Http\Controllers\Api\MD;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JenisMasukan;
use Illuminate\Support\Facades\Validator;

class AdminJenisMasukanController extends Controller
{
     /**
     * GET - List Jenis Masukan dengan pagination & search
     */
    public function index(Request $request)
    {
        $query = JenisMasukan::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('jenis_masukan', 'like', "%$s%");
        }

        $data = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $data
        ]);
    }

    /**
     * POST - Create Jenis Masukan
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_masukan' => 'required|string|max:100|unique:jenis_masukans,jenis_masukan'
        ]);

        if ($validator->fails()) {
            $field = array_key_first($validator->errors()->toArray());
            $msg   = $validator->errors()->first();
            throw new \Exception("VALIDATION_ERROR:::{$field}:::{$msg}");
        }

        $jm = JenisMasukan::create([
            'jenis_masukan' => $request->jenis_masukan
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis Masukan berhasil ditambahkan',
            'data' => $jm
        ]);
    }

    /**
     * POST - Update Jenis Masukan
     */
    public function update(Request $request, $id)
    {
        $jm = JenisMasukan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'jenis_masukan' => "required|string|max:100|unique:jenis_masukans,jenis_masukan,{$id},id_jenis_masukan"
        ]);

        if ($validator->fails()) {
            $field = array_key_first($validator->errors()->toArray());
            $msg   = $validator->errors()->first();
            throw new \Exception("VALIDATION_ERROR:::{$field}:::{$msg}");
        }

        $jm->update([
            'jenis_masukan' => $request->jenis_masukan
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis Masukan berhasil diperbarui',
            'data' => $jm
        ]);
    }

    /**
     * DELETE - Hapus Jenis Masukan
     */
    public function destroy($id)
    {
        $jm = JenisMasukan::findOrFail($id);
        $jm->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Jenis Masukan berhasil dihapus'
        ]);
    }
}
