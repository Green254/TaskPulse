<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Projects;

class Tasks extends Model
{
    /** @use HasFactory<\Database\Factories\TasksFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'project_id',
        'assigned_to',
        'status',
    ];

    public function project()
{
    return $this->belongsTo(Projects::class);
}

public function assignedTo()
{
    return $this->belongsTo(User::class, 'assigned_to');
}

}
