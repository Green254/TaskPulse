<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagerSubordinate extends Model
{
    use HasFactory;

    protected $fillable = [
        'manager_id',
        'subordinate_id',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinate()
    {
        return $this->belongsTo(User::class, 'subordinate_id');
    }
}
