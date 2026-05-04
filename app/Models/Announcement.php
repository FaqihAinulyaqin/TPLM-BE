<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'class_id',
        'topic_id',
        'teacher_id',
        'type',
        'title',
        'description',
        'due_date',
        'allow_comments',
        'is_reused',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'allow_comments' => 'boolean',
        'is_reused' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function submissions() 
    {
        return $this->hasMany(Submission::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function isPastDue()
    {
        return $this->due_date && now()->isAfter($this->due_date);
    }

     // Query Scopes
    public function scopeForClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
