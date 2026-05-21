<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class ClassController extends Controller
{
    public function store(Request $request)
    {
        try {
            if (!$request->user()->isTeacher()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru yang bisa membuat kelas'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'subject' => 'required|string|max:255',
            ], [
                'name.required' => 'Nama kelas harus diisi',
                'name.max' => 'Nama kelas maksimal 255 karakter',
                'description.max' => 'Deskripsi maksimal 1000 karakter',
                'subject.required' => 'Mata pelajaran harus diisi',
                'subject.max' => 'Mata pelajaran maksimal 255 karakter',
            ]);

            $class = ClassRoom::create([
                'teacher_id' => $request->user()->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'subject' => $validated['subject'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kelas berhasil dibuat',
                'data' => $class->load('teacher')
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function join(Request $request)
    {
        try {
            if (!$request->user()->isStudent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya siswa yang bisa bergabung ke kelas'
                ], 403);
            }

            $validated = $request->validate([
                'class_code' => 'required|string|exists:classes,class_code'
            ], [
                'class_code.required' => 'Kode kelas harus diisi',
                'class_code.exists' => 'Kode kelas tidak valid',
            ]);

            $class = ClassRoom::where('class_code', $validated['class_code'])->first();

            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tidak ditemukan'
                ], 404);
            }

            if ($class->members()->where('user_id', $request->user()->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah terdaftar di kelas ini'
                ], 400);
            }

            $class->members()->attach($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil bergabung ke kelas',
                'data' => $class->load('teacher', 'members')
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal bergabung ke kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $class = ClassRoom::with(['teacher', 'members'])->findOrFail($id);

            $user = request()->user();
            $isMember = $class->members()->where('user_id', $user->id)->exists();
            $isTeacher = $class->teacher_id === $user->id;

            if (!$isMember && !$isTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan anggota kelas ini'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'class' => $class,
                    'teacher' => $class->teacher,
                    'students' => $class->members
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->isTeacher()) {
                $classes = $user->teachingClasses()->with('members')->get();
            } else {
                $classes = $user->enrolledClasses()->with('teacher')->get();
            }

            return response()->json([
                'success' => true,
                'data' => $classes
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $class = ClassRoom::findOrFail($id);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah kelas ini'
                ], 403);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'subject' => 'sometimes|required|string|max:255',
            ], [
                'name.required' => 'Nama kelas harus diisi',
                'name.max' => 'Nama kelas maksimal 255 karakter',
                'description.max' => 'Deskripsi maksimal 1000 karakter',
                'subject.required' => 'Mata pelajaran harus diisi',
                'subject.max' => 'Mata pelajaran maksimal 255 karakter',
            ]);

            $class->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Kelas berhasil diupdate',
                'data' => $class
            ]);

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
                'message' => 'Gagal mengupdate kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $class = ClassRoom::findOrFail($id);

            if ($class->teacher_id !== request()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus kelas ini'
                ], 403);
            }

            $class->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kelas berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
