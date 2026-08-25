<?php

use App\Services\ContentHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $html = app(ContentHtml::class);

        if (Schema::hasTable('page_contents')) {
            DB::table('page_contents')->orderBy('page_key')->each(function (object $page) use ($html): void {
                DB::table('page_contents')->where('page_key', $page->page_key)->update([
                    'intro_content' => $html->fromMarkdown($page->intro_content),
                    'bottom_content' => $html->fromMarkdown($page->bottom_content),
                ]);
            });
        }

        if (Schema::hasTable('location_contents')) {
            DB::table('location_contents')->orderBy('location_id')->chunkById(100, function ($contents) use ($html): void {
                foreach ($contents as $content) {
                    DB::table('location_contents')->where('location_id', $content->location_id)->update([
                        'intro_content' => $html->fromMarkdown($content->intro_content),
                        'bottom_content' => $html->fromMarkdown($content->bottom_content),
                    ]);
                }
            }, 'location_id');
        }

        if (Schema::hasTable('policy_versions')) {
            DB::table('policy_versions')->orderBy('id')->chunkById(100, function ($policies) use ($html): void {
                foreach ($policies as $policy) {
                    $content = $html->fromMarkdown($policy->content);
                    DB::table('policy_versions')->where('id', $policy->id)->update([
                        'content' => $content,
                        'content_hash' => hash('sha256', $content),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // HTML cannot be converted back to the exact original Markdown losslessly.
    }
};
