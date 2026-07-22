<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileMediaController;
use App\Http\Controllers\ProviderOnboardingController;
use App\Http\Controllers\PublicAgencyController;
use App\Http\Controllers\PublicDirectoryController;
use App\Http\Controllers\Seo\DirectoryConfigurationController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Staff\ProfileReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicDirectoryController::class, 'home'])->name('directory.home');
Route::get('/escort/{profile}', [PublicDirectoryController::class, 'profile'])->name('directory.profiles.show');
Route::get('/agencies', [PublicAgencyController::class, 'index'])->name('directory.agencies.index');
Route::get('/agency/{agency}', [PublicAgencyController::class, 'show'])->name('directory.agencies.show');
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

    Route::prefix('staff')->name('staff.')->group(function () {
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
    });
});

require __DIR__.'/auth.php';

Route::get('/{city}-escorts/page/{page}', [PublicDirectoryController::class, 'city'])
    ->whereNumber('page')->name('directory.cities.page');
Route::get('/{city}/{neighbourhood}-escorts/page/{page}', [PublicDirectoryController::class, 'neighbourhood'])
    ->whereNumber('page')->name('directory.neighbourhoods.page');
Route::get('/{city}-escorts', [PublicDirectoryController::class, 'city'])->name('directory.cities.show');
Route::get('/{city}/{neighbourhood}-escorts', [PublicDirectoryController::class, 'neighbourhood'])->name('directory.neighbourhoods.show');
