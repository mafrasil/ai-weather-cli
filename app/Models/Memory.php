<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'context',
    ];

    protected $casts = [
        'value' => 'json',
        'context' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeForKeyPattern($query, string $pattern)
    {
        return $query->where('key', 'like', $pattern);
    }
}
