<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ClassRoom;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    public function store(Request $request, $classId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru kelas yang bisa membuat pengumuman'
                ], 403);
            }

            $validated = $request->validate([
                'type' => 'required|in:material,assignment,quiz,question',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:5000',
                'topic_id' => 'nullable|exists:topics,id',
                'allow_comments' => 'boolean',
                'attachments.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip,rar'
            ], [
                'type.required' => 'Tipe pengumuman harus dipilih',
                'type.in' => 'Tipe pengumuman tidak valid',
                'title.required' => 'Judul harus diisi',
                'title.max' => 'Judul maksimal 255 karakter',
                'description.max' => 'Deskripsi maksimal 5000 karakter',
                'topic_id.exists' => 'Topik tidak ditemukan',
                'attachments.*.file' => 'File tidak valid',
                'attachments.*.max' => 'Ukuran file maksimal 10MB',
                'attachments.*.mimes' => 'Format file tidak didukung',
            ]);

            $announcement = Announcement::create([
                'class_id' => $classId,
                'topic_id' => $validated['topic_id'] ?? null,
                'teacher_id' => $request->user()->id,
                'type' => $validated['type'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'allow_comments' => $validated['allow_comments'] ?? true,
                'is_reused' => false,
            ]);

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    try {
                        $fileName = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('attachments', $fileName, 'public');

                        Attachment::create([
                            'announcement_id' => $announcement->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $filePath,
                            'file_type' => $file->getClientMimeType(),
                        ]);
                    } catch (Exception $e) {
                        // Log error but continue with other files
                        Log::error('File upload error: ' . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil dibuat',
                'data' => $announcement->load('attachments', 'topic')
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
                'message' => 'Gagal membuat pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reuse(Request $request, $classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $originalAnnouncement = Announcement::findOrFail($announcementId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk reuse pengumuman'
                ], 403);
            }

            // Create copy of announcement
            $newAnnouncement = $originalAnnouncement->replicate();
            $newAnnouncement->class_id = $classId;
            $newAnnouncement->teacher_id = $request->user()->id;
            $newAnnouncement->is_reused = true;
            $newAnnouncement->save();

            // Copy attachments
            foreach ($originalAnnouncement->attachments as $attachment) {
                Attachment::create([
                    'announcement_id' => $newAnnouncement->id,
                    'file_name' => $attachment->file_name,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil di-reuse',
                'data' => $newAnnouncement->load('attachments', 'topic')
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal reuse pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addToTopic(Request $request, $classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah pengumuman ini'
                ], 403);
            }

            $validated = $request->validate([
                'topic_id' => 'required|exists:topics,id'
            ], [
                'topic_id.required' => 'ID topik harus diisi',
                'topic_id.exists' => 'Topik tidak ditemukan',
            ]);

            $announcement->update(['topic_id' => $validated['topic_id']]);

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil ditambahkan ke topik',
                'data' => $announcement->load('topic')
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
                'message' => 'Gagal menambahkan pengumuman ke topik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index($classId)
    {
        try {
            ClassRoom::findOrFail($classId);

            $announcements = Announcement::where('class_id', $classId)
                ->with(['teacher', 'topic', 'attachments', 'comments.user'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $announcements
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($classId, $announcementId)
    {
        try {
            $announcement = Announcement::where('class_id', $classId)
                ->with(['teacher', 'topic', 'attachments', 'comments.user', 'grades.student'])
                ->findOrFail($announcementId);

            return response()->json([
                'success' => true,
                'data' => $announcement
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);

            if ($class->teacher_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah pengumuman ini'
                ], 403);
            }

            $validated = $request->validate([
                'type' => 'sometimes|in:material,assignment,quiz,question',
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string|max:5000',
                'allow_comments' => 'boolean',
            ], [
                'type.in' => 'Tipe pengumuman tidak valid',
                'title.max' => 'Judul maksimal 255 karakter',
                'description.max' => 'Deskripsi maksimal 5000 karakter',
            ]);

            $announcement->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil diupdate',
                'data' => $announcement
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
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
                'message' => 'Gagal mengupdate pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);

            if ($class->teacher_id !== request()->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus pengumuman ini'
                ], 403);
            }

            // Delete attachments from storage
            foreach ($announcement->attachments as $attachment) {
                try {
                    Storage::disk('public')->delete($attachment->file_path);
                } catch (Exception $e) {
                    Log::error('Failed to delete file: ' . $e->getMessage());
                }
            }

            $announcement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengumuman',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
