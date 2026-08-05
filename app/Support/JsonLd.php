<?php

namespace App\Support;

class JsonLd
{
    /** @param  array<int, array{name: string, url: string}>  $items */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /** @param  array<int, string>  $urls */
    public static function itemList(array $urls): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => collect($urls)->values()->map(fn (string $url, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $url,
            ])->all(),
        ];
    }

    /** @param  array<int, array{question: string, answer: string}>  $pairs */
    public static function faqPage(array $pairs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($pairs)->map(fn (array $pair) => [
                '@type' => 'Question',
                'name' => $pair['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $pair['answer'],
                ],
            ])->all(),
        ];
    }

    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => route('directory.home'),
        ];
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => route('directory.home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('directory.search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param  array<int, array<string, mixed>>  $schemas */
    public static function script(array $schemas): string
    {
        return collect($schemas)
            ->map(fn (array $schema) => '<script type="application/ld+json">'
                .json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                .'</script>')
            ->implode("\n");
    }
}
