<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\SubmissionController;

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'service' => 'classroom-api',
        'version' => '1.0.0'
    ]);
});

// Public routes
Route::middleware('throttle:register')->post('/register', [AuthController::class, 'register']);
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);


// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Class routes
    Route::prefix('classes')->group(function () {
        Route::get('/', [ClassController::class, 'index']); // Get all user's classes
        Route::post('/', [ClassController::class, 'store'])->middleware('throttle:create-class');
        Route::post('/join', [ClassController::class, 'join']); // Join class (student)
        Route::get('/{id}', [ClassController::class, 'show']); // Get class details with members
        Route::put('/{id}', [ClassController::class, 'update']); // Update class (teacher)
        Route::delete('/{id}', [ClassController::class, 'destroy']); // Delete class (teacher)

        // Topic routes (within class)
        Route::prefix('{classId}/topics')->group(function () {
            Route::get('/', [TopicController::class, 'index']); // Get all topics
            Route::post('/', [TopicController::class, 'store']); // Create topic (teacher)
            Route::put('/{topicId}', [TopicController::class, 'update']); // Update topic (teacher)
            Route::delete('/{topicId}', [TopicController::class, 'destroy']); // Delete topic (teacher)
        });

        // Announcement routes (within class)
        Route::prefix('{classId}/announcements')->group(function () {
            Route::get('/', [AnnouncementController::class, 'index']); // Get all announcements
            Route::post('/', [AnnouncementController::class, 'store'])->middleware('throttle:create-announcement');
            Route::get('/{announcementId}', [AnnouncementController::class, 'show']); // Get announcement detail
            Route::put('/{announcementId}', [AnnouncementController::class, 'update']); // Update announcement (teacher)
            Route::delete('/{announcementId}', [AnnouncementController::class, 'destroy']); // Delete announcement (teacher)
            Route::post('/{announcementId}/attachments', [AnnouncementController::class, 'addAttachment']); // Update announcement file section
            Route::delete('/{announcementId}/attachments/{attachmentId}', [AnnouncementController::class, 'deleteAttachment']); // Delete announcement file section
            
            // Reuse and add to topic
            Route::post('/{announcementId}/reuse', [AnnouncementController::class, 'reuse']); // Reuse announcement
            Route::post('/{announcementId}/add-to-topic', [AnnouncementController::class, 'addToTopic']); // Add to topic

            // Comment routes (within announcement)
            Route::prefix('{announcementId}/comments')->group(function () {
                Route::get('/', [CommentController::class, 'index']); // Get all comments
                Route::post('/', [CommentController::class, 'store'])->middleware('throttle:add-comment'); // Add comment
                Route::put('/{commentId}', [CommentController::class, 'update']); // Update comment
                Route::delete('/{commentId}', [CommentController::class, 'destroy']); // Delete comment
            });

            // Grade routes (within announcement)
            Route::prefix('{announcementId}/grades')->group(function () {
                Route::get('/', [GradeController::class, 'index']); // Get all grades (teacher)
                Route::post('/', [GradeController::class, 'store']); // Add single grade (teacher)
                Route::post('/batch', [GradeController::class, 'storeBatch'])->middleware('throttle:batch-grade'); // Add multiple grades (teacher)
                Route::put('/{gradeId}', [GradeController::class, 'update']); // Update grade (teacher)
                Route::delete('/{gradeId}', [GradeController::class, 'destroy']); // Delete grade (teacher)
                Route::get('/task-grades', [GradeController::class, 'taskGrades']); // Get grade for a specific assignment
            });

            //Submission routes (within announcement)
            Route::prefix('{announcementId}/submissions')->group(function () {
                Route::get('/', [SubmissionController::class, 'index']); // Get all submissions (teacher)
                Route::post('/', [SubmissionController::class, 'store']); // Submit assignment (student)
                Route::get('/my-submission', [SubmissionController::class, 'mySubmission']); // Get my submission
                Route::put('/{submissionId}', [SubmissionController::class, 'update']); // Update submission
                Route::post('/{submissionId}/attachments', [SubmissionController::class, 'addFile']); // Add submission file
                Route::delete('/{submissionId}/attachments/{fileId}', [SubmissionController::class, 'deleteFile']); // Delete submission file
                Route::delete('/{submissionId}', [SubmissionController::class, 'destroy']); // Delete submission (teacher)
            });
        });

        // Student grades in class
        Route::get('/{classId}/my-grades', [GradeController::class, 'myGrades']); // get all grades in a class (student)
    });
});