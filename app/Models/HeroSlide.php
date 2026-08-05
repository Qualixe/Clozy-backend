<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    protected $fillable = [
        'eyebrow',
        'ghost_text',
        'heading_line_1',
        'heading_line_2',
        'body',
        'cta_label',
        'cta_href',
        'image',
        'gradient_from',
        'gradient_to',
        'accent_color',
        'text_color',
        'position',
    ];
}
