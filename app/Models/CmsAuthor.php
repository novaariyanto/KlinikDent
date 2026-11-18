<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsAuthor extends Model
{
    protected $table = 'cms_authors';

    protected $fillable = [
        'name',
        'slug',
        'role_title',
        'bio',
        'avatar_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CmsPost::class, 'author_id');
    }
}
