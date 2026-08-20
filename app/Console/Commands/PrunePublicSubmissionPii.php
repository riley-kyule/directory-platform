<?php

namespace App\Console\Commands;

use App\Models\ProfileReport;
use App\Models\Review;
use Illuminate\Console\Command;

class PrunePublicSubmissionPii extends Command
{
    protected $signature = 'privacy:prune-public-submission-pii';

    protected $description = 'Remove expired personal and abuse-fingerprint data from moderated reviews and closed reports';

    public function handle(): int
    {
        $reviewCutoff = now()->subDays(config('operations.review_pii_retention_days'));
        $reportCutoff = now()->subDays(config('operations.report_pii_retention_days'));

        $reviews = Review::query()
            ->whereIn('status', ['published', 'rejected'])
            ->whereNotNull('moderated_at')
            ->where('moderated_at', '<', $reviewCutoff)
            ->where(fn ($query) => $query
                ->whereNotNull('reviewer_email')
                ->orWhereNotNull('reviewer_email_hash')
                ->orWhereNotNull('source_fingerprint'))
            ->update([
                'reviewer_email' => null,
                'reviewer_email_hash' => null,
                'source_fingerprint' => null,
            ]);

        $reports = ProfileReport::query()
            ->whereIn('status', ['resolved', 'dismissed'])
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', $reportCutoff)
            ->where(fn ($query) => $query
                ->whereNotNull('reporter_email')
                ->orWhereNotNull('reporter_email_hash')
                ->orWhereNotNull('source_fingerprint')
                ->orWhereNotNull('reporter_user_id'))
            ->update([
                'reporter_user_id' => null,
                'reporter_email' => null,
                'reporter_email_hash' => null,
                'source_fingerprint' => null,
            ]);

        $this->info("Redacted {$reviews} review submission(s) and {$reports} report submission(s).");

        return self::SUCCESS;
    }
}
