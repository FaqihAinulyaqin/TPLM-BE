<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
