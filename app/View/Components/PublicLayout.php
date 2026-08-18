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
        public readonly ?string $structuredData = null,
        public readonly ?string $socialImage = null,
        public readonly string $socialType = 'website',
        public readonly ?string $previousUrl = null,
        public readonly ?string $nextUrl = null,
    ) {}

    public function render(): View
    {
        return view('layouts.public');
    }
}
