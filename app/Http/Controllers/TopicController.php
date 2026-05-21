<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class TopicController extends Controller
{
    public function store(Request $request, $classId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru kelas yang bisa membuat topik'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255'
            ], [
                'name.required' => 'Nama topik harus diisi',
                'name.max' => 'Nama topik maksimal 255 karakter',
            ]);

            $topic = Topic::create([
                'class_id' => $classId,
                'name' => $validated['name']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Topik berhasil dibuat',
                'data' => $topic
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat topik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $classId, $topicId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $topic = Topic::where('class_id', $classId)->findOrFail($topicId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah topik ini'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255'
            ], [
                'name.required' => 'Nama topik harus diisi',
                'name.max' => 'Nama topik maksimal 255 karakter',
            ]);

            $topic->update(['name' => $validated['name']]);

            return response()->json([
                'success' => true,
                'message' => 'Topik berhasil diupdate',
                'data' => $topic
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Topik tidak ditemukan'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate topik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($classId, $topicId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $topic = Topic::where('class_id', $classId)->findOrFail($topicId);

            if ($class->teacher_id !== request()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus topik ini'
                ], 403);
            }

            $topic->delete();

            return response()->json([
                'success' => true,
                'message' => 'Topik berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Topik tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus topik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index($classId)
    {
        try {
            // Check if class exists
            ClassRoom::findOrFail($classId);

            $topics = Topic::where('class_id', $classId)
                ->with('announcements')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $topics
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar topik',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}