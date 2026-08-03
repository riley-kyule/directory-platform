<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Aggregate, day-scoped popularity counts for free-text search terms.
 * Deliberately holds nothing that identifies who searched — no user,
 * session, or IP — and only ever gets a row once a term has already
 * crossed the daily threshold; see SearchTermLogger.
 */
#[Fillable(['search_date', 'term', 'search_count'])]
class SearchTermLog extends Model
{
    // Deliberately no cast on search_date: SearchTermLogger always writes and
    // queries it as a plain "Y-m-d" string. A 'date' cast round-trips through
    // a different stored format on write than the raw string used in
    // updateOrCreate()'s WHERE condition, which broke the unique constraint
    // on the second write for the same term/day.
}
