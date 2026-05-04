<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'announcement_id',
        'student_id',
        'text_content',
        'status',
        'submitted_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function attachments()
    {
        return $this->hasMany(SubmissionAttachment::class);
    }

    public function grade()
    {
        return $this->hasOne(Grade::class, 'student_id', 'student_id')->where('announcement_id', $this->announcement_id);           
    }
}
