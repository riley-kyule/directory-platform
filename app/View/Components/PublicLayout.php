<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    public function __construct(
        public readonly string $metaTitle,
        public readonly string $metaDescription,
        public readonly string $canonicalUrl,
        public readonly string $robots = 'index,follow',
    ) {}

    public function render(): View
    {
        return view('layouts.public');
    }
}
