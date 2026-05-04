<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\ClassRoom;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class GradeController extends Controller
{
    public function store(Request $request, $classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru kelas yang bisa menambahkan nilai'
                ], 403);
            }

            // cek type announcement
            if ($announcement->type !== 'assignment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Penilaian hanya bisa dilakukan pada tugas (assignment)'
                ], 400);
            }

            $validated = $request->validate([
                'student_id' => 'required|exists:users,id',
                'score' => 'required|numeric|min:0|max:100',
                'comment' => 'nullable|string|max:1000'
            ], [
                'student_id.required' => 'ID siswa harus diisi',
                'student_id.exists' => 'Siswa tidak ditemukan',
                'score.required' => 'Nilai harus diisi',
                'score.numeric' => 'Nilai harus berupa angka',
                'score.min' => 'Nilai minimal 0',
                'score.max' => 'Nilai maksimal 100',
                'comment.max' => 'Komentar maksimal 1000 karakter',
            ]);

            // Check if student exists and has student role
            $student = User::find($validated['student_id']);
            if (!$student || !$student->isStudent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User bukan siswa'
                ], 400);
            }

            if (!$class->members()->where('user_id', $validated['student_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa bukan anggota kelas ini'
                ], 400);
            }

            $grade = Grade::updateOrCreate(
                [
                    'announcement_id' => $announcementId,
                    'student_id' => $validated['student_id']
                ],
                [
                    'score' => $validated['score'],
                    'comment' => $validated['comment'] ?? null
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Nilai berhasil disimpan',
                'data' => $grade->load('student')
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
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
                'message' => 'Gagal menyimpan nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeBatch(Request $request, $classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru kelas yang bisa menambahkan nilai'
                ], 403);
            }

            // cek type announcement
            if ($announcement->type !== 'assignment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Penilaian hanya bisa dilakukan pada tugas (assignment'
                ], 400);
            }

            $validated = $request->validate([
                'grades' => 'required|array|min:1',
                'grades.*.student_id' => 'required|exists:users,id',
                'grades.*.score' => 'required|numeric|min:0|max:100',
                'grades.*.comment' => 'nullable|string|max:1000'
            ], [
                'grades.required' => 'Data nilai harus diisi',
                'grades.array' => 'Format data nilai tidak valid',
                'grades.min' => 'Minimal 1 nilai harus diisi',
                'grades.*.student_id.required' => 'ID siswa harus diisi',
                'grades.*.student_id.exists' => 'Siswa tidak ditemukan',
                'grades.*.score.required' => 'Nilai harus diisi',
                'grades.*.score.numeric' => 'Nilai harus berupa angka',
                'grades.*.score.min' => 'Nilai minimal 0',
                'grades.*.score.max' => 'Nilai maksimal 100',
            ]);

            $savedGrades = [];
            $errors = [];

            foreach ($validated['grades'] as $index => $gradeData) {
                // Check if student is member
                if (!$class->members()->where('user_id', $gradeData['student_id'])->exists()) {
                    $errors[] = "Siswa dengan ID {$gradeData['student_id']} bukan anggota kelas";
                    continue;
                }

                $grade = Grade::updateOrCreate(
                    [
                        'announcement_id' => $announcementId,
                        'student_id' => $gradeData['student_id']
                    ],
                    [
                        'score' => $gradeData['score'],
                        'comment' => $gradeData['comment'] ?? null
                    ]
                );

                $savedGrades[] = $grade;
            }

            $response = [
                'success' => true,
                'message' => count($savedGrades) . ' nilai berhasil disimpan',
                'data' => $savedGrades
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
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
                'message' => 'Gagal menyimpan nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function myGrades(Request $request, $classId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $user = $request->user();

            if (!$class->members()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan anggota kelas ini'
                ], 403);
            }

            $grades = Grade::whereHas('announcement', function ($query) use ($classId) {
                $query->where('class_id', $classId);
            })
                ->where('student_id', $user->id)
                ->with(['announcement'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $grades
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function taskGrades(Request $request, $classId, $announcementId)
    {
        try {
            $user = $request->user();
            $class = ClassRoom::findOrFail($classId);

            // make sure students are members of the class
            if (!$class->members()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan anggota kelas ini'
                ], 403);
            }

            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);

            if ($announcement->type !== 'assignment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Nilai hanya tersedia untuk tugas (assignment)'
                ], 400);
            }

            // Ambil nilai siswa untuk assignment ini
            $grade = Grade::where('announcement_id', $announcementId)
                        ->where('student_id', $user->id)
                        ->with('announcement')
                        ->first();

            return response()->json([
                'success' => true,
                'data' => $grade,
                'message' => $grade ? null : 'Nilai belum tersedia'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function index($classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $user = request()->user();

            if ($class->teacher_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru kelas yang bisa melihat semua nilai'
                ], 403);
            }

            $grades = Grade::where('announcement_id', $announcementId)
                ->with('student')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $grades
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $classId, $announcementId, $gradeId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);
            $grade = Grade::where('announcement_id', $announcementId)->findOrFail($gradeId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah nilai'
                ], 403);
            }

            // cek type announcement
            if ($announcement->type !== 'assignment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Penilaian hanya bisa dilakukan pada tugas (assignment)'
                ], 400);
            }

            $validated = $request->validate([
                'score' => 'sometimes|numeric|min:0|max:100',
                'comment' => 'nullable|string|max:1000'
            ], [
                'score.numeric' => 'Nilai harus berupa angka',
                'score.min' => 'Nilai minimal 0',
                'score.max' => 'Nilai maksimal 100',
                'comment.max' => 'Komentar maksimal 1000 karakter',
            ]);

            $grade->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Nilai berhasil diupdate',
                'data' => $grade
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai tidak ditemukan'
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
                'message' => 'Gagal mengupdate nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($classId, $announcementId, $gradeId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $grade = Grade::where('announcement_id', $announcementId)->findOrFail($gradeId);

            if ($class->teacher_id !== request()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus nilai'
                ], 403);
            }

            $grade->delete();

            return response()->json([
                'success' => true,
                'message' => 'Nilai berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
