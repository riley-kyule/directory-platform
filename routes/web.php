<?php

use App\Http\Controllers\Admin\DirectorySettingsController;
use App\Http\Controllers\Admin\PolicyManagementController;
use App\Http\Controllers\ModerationAppealController;
use App\Http\Controllers\PolicyPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileMediaController;
use App\Http\Controllers\ProfileReportController;
use App\Http\Controllers\ProviderOnboardingController;
use App\Http\Controllers\ProviderProfileController;
use App\Http\Controllers\PublicAgencyController;
use App\Http\Controllers\PublicDirectoryController;
use App\Http\Controllers\PublicSearchController;
use App\Http\Controllers\Seo\DirectoryConfigurationController;
use App\Http\Controllers\Seo\RedirectManagementController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Staff\ModerationController;
use App\Http\Controllers\Staff\ProfileManagementController;
use App\Http\Controllers\Staff\ProfileReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicDirectoryController::class, 'home'])->name('directory.home');
Route::get('/escort/{profile}', [PublicDirectoryController::class, 'profile'])->name('directory.profiles.show');
Route::get('/escort/{profile:slug}/report', [ProfileReportController::class, 'create'])->name('directory.profiles.report.create');
Route::post('/escort/{profile:slug}/report', [ProfileReportController::class, 'store'])
    ->middleware('throttle:5,10')
    ->name('directory.profiles.report.store');
Route::get('/agencies', [PublicAgencyController::class, 'index'])->name('directory.agencies.index');
Route::get('/agency/{agency}', [PublicAgencyController::class, 'show'])->name('directory.agencies.show');
Route::get('/search', [PublicSearchController::class, 'index'])->name('directory.search');
Route::get('/terms', [PolicyPageController::class, 'show'])->defaults('policyType', 'terms')->name('policies.terms');
Route::get('/privacy', [PolicyPageController::class, 'show'])->defaults('policyType', 'privacy')->name('policies.privacy');
Route::get('/provider-policy', [PolicyPageController::class, 'show'])->defaults('policyType', 'provider')->name('policies.provider');
Route::get('/media-policy', [PolicyPageController::class, 'show'])->defaults('policyType', 'media')->name('policies.media');
Route::get('/agency-policy', [PolicyPageController::class, 'show'])->defaults('policyType', 'agency')->name('policies.agency');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemaps.index');
Route::get('/sitemaps/editorial.xml', [SitemapController::class, 'editorial'])->name('sitemaps.editorial');
Route::get('/sitemaps/locations-{page}.xml', [SitemapController::class, 'locations'])->whereNumber('page')->name('sitemaps.locations');
Route::get('/sitemaps/profiles-{page}.xml', [SitemapController::class, 'profiles'])->whereNumber('page')->name('sitemaps.profiles');
Route::get('/sitemaps/agencies-{page}.xml', [SitemapController::class, 'agencies'])->whereNumber('page')->name('sitemaps.agencies');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [ProviderOnboardingController::class, 'index'])->name('index');
        Route::post('/agency', [ProviderOnboardingController::class, 'storeAgency'])->name('agency.store');
        Route::get('/profiles/create', [ProviderOnboardingController::class, 'createProfile'])->name('profiles.create');
        Route::post('/profiles', [ProviderOnboardingController::class, 'storeProfile'])->name('profiles.store');
        Route::post('/profiles/{profile}/submit', [ProviderOnboardingController::class, 'submitProfile'])->name('profiles.submit');
    });

    Route::prefix('profiles/{profile}/media')->name('profiles.media.')->group(function () {
        Route::get('/', [ProfileMediaController::class, 'index'])->name('index');
        Route::post('/', [ProfileMediaController::class, 'store'])->name('store');
        Route::get('/{image}/{slot}', [ProfileMediaController::class, 'preview'])->name('preview');
        Route::delete('/{image}', [ProfileMediaController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('my-profiles/{profile}')->name('provider.profiles.')->group(function () {
        Route::get('/', [ProviderProfileController::class, 'show'])->name('show');
        Route::get('/edit', [ProviderProfileController::class, 'edit'])->name('edit');
        Route::patch('/', [ProviderProfileController::class, 'update'])->name('update');
        Route::post('/renewal', [ProviderProfileController::class, 'requestRenewal'])->name('renewal.store');
        Route::post('/moderation-appeals', [ModerationAppealController::class, 'store'])->name('appeals.store');
    });

    Route::prefix('admin/settings')->name('admin.settings.')->group(function () {
        Route::get('/', [DirectorySettingsController::class, 'index'])->name('index');
        Route::patch('/', [DirectorySettingsController::class, 'update'])->name('update');
        Route::patch('/packages/{package}', [DirectorySettingsController::class, 'updatePackage'])->name('packages.update');
        Route::post('/durations', [DirectorySettingsController::class, 'storeDuration'])->name('durations.store');
        Route::patch('/durations/{duration}', [DirectorySettingsController::class, 'updateDuration'])->name('durations.update');
    });

    Route::prefix('admin/policies')->name('admin.policies.')->group(function () {
        Route::get('/', [PolicyManagementController::class, 'index'])->name('index');
        Route::get('/{policyType}/edit', [PolicyManagementController::class, 'edit'])->name('edit');
        Route::put('/{policyType}', [PolicyManagementController::class, 'save'])->name('save');
    });

    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::get('/moderation/{report:public_id}', [ModerationController::class, 'show'])->name('moderation.show');
        Route::patch('/moderation/{report:public_id}', [ModerationController::class, 'update'])->name('moderation.update');
        Route::patch('/moderation-appeals/{appeal:public_id}', [ModerationController::class, 'reviewAppeal'])->name('moderation.appeals.review');
        Route::get('/directory', [ProfileManagementController::class, 'index'])->name('directory.index');
        Route::get('/directory/{profile}', [ProfileManagementController::class, 'show'])->name('directory.show');
        Route::patch('/directory/{profile}', [ProfileManagementController::class, 'update'])->name('directory.update');
        Route::get('/profiles', [ProfileReviewController::class, 'index'])->name('profiles.index');
        Route::get('/profiles/{packageRequest}', [ProfileReviewController::class, 'show'])->name('profiles.show');
        Route::patch('/profiles/{packageRequest}', [ProfileReviewController::class, 'update'])->name('profiles.update');
    });

    Route::prefix('seo')->name('seo.')->group(function () {
        Route::get('/directory', [DirectoryConfigurationController::class, 'index'])->name('directory.index');
        Route::get('/locations/create', [DirectoryConfigurationController::class, 'createLocation'])->name('locations.create');
        Route::post('/locations', [DirectoryConfigurationController::class, 'storeLocation'])->name('locations.store');
        Route::get('/locations/{location}/content', [DirectoryConfigurationController::class, 'editLocation'])->name('locations.content.edit');
        Route::patch('/locations/{location}/content', [DirectoryConfigurationController::class, 'updateLocation'])->name('locations.content.update');
        Route::patch('/pages/homepage', [DirectoryConfigurationController::class, 'updateHomepage'])->name('pages.homepage.update');
        Route::patch('/pages/agencies', [DirectoryConfigurationController::class, 'updateAgencyDirectory'])->name('pages.agencies.update');
        Route::post('/taxonomies', [DirectoryConfigurationController::class, 'storeTaxonomy'])->name('taxonomies.store');
        Route::get('/redirects', [RedirectManagementController::class, 'index'])->name('redirects.index');
        Route::post('/redirects', [RedirectManagementController::class, 'store'])->name('redirects.store');
        Route::patch('/redirects/{redirect}/toggle', [RedirectManagementController::class, 'toggle'])->name('redirects.toggle');
        Route::patch('/profiles/{profile}/slug', [RedirectManagementController::class, 'updateProfileSlug'])->name('profiles.slug.update');
    });
});

require __DIR__.'/auth.php';

Route::get('/{city}-escorts/page/{page}', [PublicDirectoryController::class, 'city'])
    ->where('city', '[a-z0-9]+(?:-[a-z0-9]+)*')->whereNumber('page')->name('directory.cities.page');
Route::get('/{city}/{neighbourhood}/{micro}-escorts/page/{page}', [PublicDirectoryController::class, 'microLocation'])
    ->where([
        'city' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'neighbourhood' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'micro' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])->whereNumber('page')->name('directory.micro-locations.page');
Route::get('/{city}/{neighbourhood}-escorts/page/{page}', [PublicDirectoryController::class, 'neighbourhood'])
    ->where([
        'city' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'neighbourhood' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])->whereNumber('page')->name('directory.neighbourhoods.page');
Route::get('/{city}/{neighbourhood}/{micro}-escorts', [PublicDirectoryController::class, 'microLocation'])
    ->where([
        'city' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'neighbourhood' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'micro' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])
    ->name('directory.micro-locations.show');
Route::get('/{city}-escorts', [PublicDirectoryController::class, 'city'])
    ->where('city', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('directory.cities.show');
Route::get('/{city}/{neighbourhood}-escorts', [PublicDirectoryController::class, 'neighbourhood'])
    ->where([
        'city' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'neighbourhood' => '[a-z0-9]+(?:-[a-z0-9]+)*',
    ])->name('directory.neighbourhoods.show');

Route::fallback(fn () => response('', 404));
