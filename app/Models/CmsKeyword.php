<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsKeyword extends Model
{
    protected $table = 'cms_keywords';

    protected $fillable = [
        'keyword',
        'slug',
        'pillar',
        'intent',
        'priority',
        'status',
        'target_url',
        'post_id',
        'review_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CmsPost::class, 'post_id');
    }
}
