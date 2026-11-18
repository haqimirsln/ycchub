<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['source', 'ig_id', 'media_type', 'caption', 'permalink', 'image_path', 'posted_at'])]
class InstagramPost extends Model
{
    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('uploads/'.$this->image_path) : null;
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'VIDEO';
    }

    public function isCarousel(): bool
    {
        return $this->media_type === 'CAROUSEL_ALBUM';
    }

    public function shortCaption(int $limit = 110): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->caption)), $limit);
    }
}
