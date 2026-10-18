<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['schema' => 'array', 'draft' => 'array', 'published' => 'array', 'published_at' => 'datetime'];
    }

    public function revisions()
    {
        return $this->hasMany(ContentRevision::class)->latest('id');
    }

    public function hasDraft(): bool
    {
        return $this->draft != $this->published;
    }

    public function getStatusAttribute(): string
    {
        return $this->hasDraft() ? 'Есть черновик' : 'Опубликовано';
    }

    public function getPublicUrlAttribute(): string
    {
        return url($this->kind === 'shared' || $this->kind === 'settings' || $this->key === 'index.html' ? '/' : '/'.$this->key);
    }
}
