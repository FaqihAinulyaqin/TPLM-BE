<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Announcement;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class CommentController extends Controller
{
    public function store(Request $request, $classId, $announcementId)
    {
        try {
            $class = ClassRoom::findOrFail($classId);
            $announcement = Announcement::where('class_id', $classId)->findOrFail($announcementId);

            $user = $request->user();
            $isMember = $class->members()->where('user_id', $user->id)->exists();
            $isTeacher = $class->teacher_id === $user->id;

            if (!$isMember && !$isTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan anggota kelas ini'
                ], 403);
            }

            if (!$announcement->allow_comments && !$isTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Komentar tidak diizinkan untuk pengumuman ini'
                ], 403);
            }

            $validated = $request->validate([
                'comment' => 'required|string|max:2000'
            ], [
                'comment.required' => 'Komentar harus diisi',
                'comment.max' => 'Komentar maksimal 2000 karakter',
            ]);

            $comment = Comment::create([
                'announcement_id' => $announcementId,
                'user_id' => $user->id,
                'comment' => $validated['comment']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil ditambahkan',
                'data' => $comment->load('user')
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
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan komentar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index($classId, $announcementId)
    {
        try {
            // Verify class and announcement exist
            ClassRoom::findOrFail($classId);
            Announcement::where('class_id', $classId)->findOrFail($announcementId);

            $comments = Comment::where('announcement_id', $announcementId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $comments
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar komentar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $classId, $announcementId, $commentId)
    {
        try {
            $comment = Comment::where('announcement_id', $announcementId)->findOrFail($commentId);

            if ($comment->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk mengubah komentar ini'
                ], 403);
            }

            $validated = $request->validate([
                'comment' => 'required|string|max:2000'
            ], [
                'comment.required' => 'Komentar harus diisi',
                'comment.max' => 'Komentar maksimal 2000 karakter',
            ]);

            $comment->update(['comment' => $validated['comment']]);

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil diupdate',
                'data' => $comment
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Komentar tidak ditemukan'
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
                'message' => 'Gagal mengupdate komentar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($classId, $announcementId, $commentId)
    {
        try {
            $comment = Comment::where('announcement_id', $announcementId)->findOrFail($commentId);
            $user = request()->user();

            // Only comment owner or teacher can delete
            $class = ClassRoom::findOrFail($classId);
            if ($comment->user_id !== $user->id && $class->teacher_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk menghapus komentar ini'
                ], 403);
            }

            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Komentar berhasil dihapus'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Komentar tidak ditemukan'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus komentar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}