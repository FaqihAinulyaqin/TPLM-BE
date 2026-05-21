<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\SubmissionAttachment;
use App\Models\Announcement;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class SubmissionController extends Controller
{
    //Murid: submit tugas
    public function store(Request $request, $classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);
            $user = $request->user();

            //check type announcement
            if ($announcement->type !== 'assignment') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengumuman ini bukan assignment, tidak bisa submit tugas'
                ], 403);
            }            

            // cek jika murid
            if (!$user->isStudent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya siswa yang bisa submit tugas'
                ], 403);
            }

            // cek jika angggota kelas
            if (!$class->members()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan anggota kelas ini'
                ], 403);
            }

            // cek jika sudah submit
            $existing = Submission::where('announcement_id', $announcementId)
                                  ->where('student_id', $user->id)
                                  ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda sudah submit tugas ini. Gunakan endpoint update untuk mengubah submission.'
                ], 400);
            }

            $validated = $request->validate([
                'text_content' => 'nullable|string|max:10000',
                'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip,rar'
            ], [
                'text_content.max' => 'Teks maksimal 10000 karakter',
                'attachments.*.file' => 'File tidak valid',
                'attachments.*.max' => 'Ukuran file maksimal 10MB',
                'attachments.*.mimes' => 'Format file tidak didukung',
            ]);

            DB::beginTransaction();

            // Tentukan status (terlambat atau tidak)
            $status = 'submitted';
            if ($announcement->due_date && now()->isAfter($announcement->due_date)) {
                $status = 'late';
            }

            // buat submission
            $submission = Submission::create([
                'announcement_id' => $announcementId,
                'student_id' => $user->id,
                'text_content' => $validated['text_content'] ?? null,
                'status' => $status,
                'submitted_at' => now(),
            ]);

            // menangani file upload
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    try {
                        $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('submissions', $fileName, 'public');

                        SubmissionAttachment::create([
                            'submission_id' => $submission->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $filePath,
                            'file_type' => $file->getClientMimeType(),
                            'file_size' => $file->getSize(),
                        ]);
                    } catch (Exception $e) {
                        Log::error('Submission file upload error: ' . $e->getMessage());
                    }
                }
            }

            Log::channel('activity')->info('Student submitted assignment', [
                'student_id' => $user->id,
                'announcement_id' => $announcementId,
                'submission_id' => $submission->id,
                'status' => $status,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $status === 'late' 
                    ? 'Tugas berhasil dikirim (terlambat)' 
                    : 'Tugas berhasil dikirim',
                'data' => $submission->load('attachments')
            ], 201);

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
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim tugas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Murid: memperbaharui tugas (sebelum dinilai)
    public function update(Request $request, $classId, $announcementId, $submissionId)
    {
        try {
            $submission = Submission::where('announcement_id', $announcementId)
                                    ->where('student_id', $request->user()->id)
                                    ->findOrFail($submissionId);

            // cek jika sudah dinilai
            if ($submission->status === 'graded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak bisa mengubah tugas yang sudah dinilai'
                ], 400);
            }

            $validated = $request->validate([
                'text_content' => 'nullable|string|max:10000',
            ], [
                'text_content.max' => 'Teks maksimal 10000 karakter',
            ]);

            $submission->update([
                'text_content' => $validated['text_content'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Submission berhasil diupdate',
                'data' => $submission->load('attachments')
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Submission tidak ditemukan'
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
                'message' => 'Gagal mengupdate submission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // STUDENT: Delete file dari submission
    public function deleteFile($classId, $announcementId, $submissionId, $fileId)
    {
        try {
            $submission = Submission::where('student_id', request()->user()->id)
                                    ->findOrFail($submissionId);

            if ($submission->status === 'graded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak bisa menghapus file dari tugas yang sudah dinilai'
                ], 400);
            }

            $attachment = SubmissionAttachment::where('submission_id', $submissionId)
                                              ->findOrFail($fileId);

            // Delete from storage
            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'File berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Student: menambahkan file ke submission yang sudah ada
    public function addFile(Request $request, $classId, $announcementId, $submissionId)
    {
        try {
            $submission = Submission::where('student_id', $request->user()->id)
                                    ->findOrFail($submissionId);

            if ($submission->status === 'graded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak bisa menambah file ke tugas yang sudah dinilai'
                ], 400);
            }

            $validated = $request->validate([
                'file' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip,rar'
            ], [
                'file.required' => 'File harus diupload',
                'file.max' => 'Ukuran file maksimal 10MB',
                'file.mimes' => 'Format file tidak didukung',
            ]);

            $file = $request->file('file');
            $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('submissions', $fileName, 'public');

            $attachment = SubmissionAttachment::create([
                'submission_id' => $submission->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File berhasil ditambahkan',
                'data' => $attachment
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
                'message' => 'Gagal menambahkan file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Murid: Get my submission
    public function mySubmission($classId, $announcementId)
    {
        try {
            $submission = Submission::where('announcement_id', $announcementId)
                                    ->where('student_id', request()->user()->id)
                                    ->with(['attachments', 'grade'])
                                    ->first();

            return response()->json([
                'success' => true,
                'data' => $submission,
                'message' => $submission ? null : 'Belum ada submission'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil submission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Guru: Get all submissions
    public function index($classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);

            if ($class->teacher_id !== request()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru kelas yang bisa melihat semua submission'
                ], 403);
            }

            $submissions = Submission::where('announcement_id', $announcementId)
                                     ->with(['student', 'attachments', 'grade'])
                                     ->orderBy('submitted_at', 'desc')
                                     ->get();

            // Get students yang belum submit
            $announcement = Announcement::with('classRoom.members')->findOrFail($announcementId);
            $submittedStudentIds = $submissions->pluck('student_id')->toArray();
            $allStudentIds = $announcement->classRoom->members()
                                          ->where('role', 'student')
                                          ->pluck('users.id')
                                          ->toArray();
            $notSubmittedIds = array_diff($allStudentIds, $submittedStudentIds);

            return response()->json([
                'success' => true,
                'data' => [
                    'submissions' => $submissions,
                    'total_students' => count($allStudentIds),
                    'submitted_count' => count($submittedStudentIds),
                    'not_submitted_count' => count($notSubmittedIds),
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil submissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Guru: Delete submission
    public function destroy($classId, $announcementId, $submissionId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);

            if ($class->teacher_id !== request()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru yang bisa menghapus submission'
                ], 403);
            }

            $submission = Submission::findOrFail($submissionId);

            // Delete files
            foreach ($submission->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $submission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Submission berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Submission tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus submission',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
