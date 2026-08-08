<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoSectionItem extends Model
{
    protected $fillable = [
        'video_url',
        'poster_url',
        'caption',
        'position',
    ];
}
