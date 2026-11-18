<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsTag extends Model
{
    protected $table = 'cms_tags';

    protected $fillable = ['name', 'slug'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(CmsPost::class, 'cms_post_tag', 'tag_id', 'post_id');
    }
}
