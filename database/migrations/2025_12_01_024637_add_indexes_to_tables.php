<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('email');
            $table->index('role');
        });

        // Classes table indexes
        Schema::table('classes', function (Blueprint $table) {
            $table->index('teacher_id');
            $table->index('class_code');
            $table->index('created_at');
        });

        // Class members table indexes
        Schema::table('class_members', function (Blueprint $table) {
            $table->index('class_id');
            $table->index('user_id');
        });

        // Topics table indexes
        Schema::table('topics', function (Blueprint $table) {
            $table->index('class_id');
        });

        // Announcements table indexes
        Schema::table('announcements', function (Blueprint $table) {
            $table->index('class_id');
            $table->index('teacher_id');
            $table->index('topic_id');
            $table->index('type');
            $table->index('created_at');
        });

        // Attachments table indexes
        Schema::table('attachments', function (Blueprint $table) {
            $table->index('announcement_id');
        });

        // Comments table indexes
        Schema::table('comments', function (Blueprint $table) {
            $table->index('announcement_id');
            $table->index('user_id');
            $table->index('created_at');
        });

        // Grades table indexes
        Schema::table('grades', function (Blueprint $table) {
            $table->index('announcement_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['role']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex(['teacher_id']);
            $table->dropIndex(['class_code']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('class_members', function (Blueprint $table) {
            $table->dropIndex(['class_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropIndex(['class_id']);
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['class_id']);
            $table->dropIndex(['teacher_id']);
            $table->dropIndex(['topic_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['announcement_id']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['announcement_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex(['announcement_id']);
            $table->dropIndex(['student_id']);
        });
    }
};
