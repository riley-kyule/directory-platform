<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\OnboardingStatus;
use App\Enums\PackageRequestStatus;
use App\Enums\ProfileStatus;
use App\Enums\ProviderType;
use App\Models\Agency;
use App\Models\DirectoryRedirect;
use App\Models\Location;
use App\Models\LocationAlias;
use App\Models\LocationContent;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Package;
use App\Models\PackageDurationOption;
use App\Models\Profile;
use App\Models\ProfileImage;
use App\Models\ProfileReport;
use App\Models\ProfileSlugHistory;
use App\Models\TaxonomyOption;
use App\Models\User;
use App\Models\VerificationCheck;
use App\Services\LocationInventoryService;
use App\Services\ModerationEnforcementService;
use App\Services\SearchTermLogger;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Not called from DatabaseSeeder — run explicitly:
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Populates realistic demo/test data across every profile state, all three
 * packages, independent and agency ownership, three locations (one deep
 * enough to cross the micro-location indexability threshold, one with zero
 * profiles on purpose), plus moderation, verification, appeals, search
 * activity, and a redirect — so every major piece of staff/public tooling
 * has something real to look at. Safe to run more than once: every prior
 * demo account (email prefixed `demo-`) and everything it owns is purged
 * first, then the full dataset is recreated fresh. It never touches
 * non-demo data.
 */
class DemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'Demo-Password-2026!';

    private Generator $faker;

    private ModerationEnforcementService $enforcement;

    private LocationInventoryService $locationInventory;

    private SearchTermLogger $searchLogger;

    /** @var array<int, string> */
    private array $summaryLines = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoDataSeeder seeds fake accounts and must never run against production.');
        }

        $this->faker = fake();
        $this->enforcement = app(ModerationEnforcementService::class);
        $this->locationInventory = app(LocationInventoryService::class);
        $this->searchLogger = app(SearchTermLogger::class);

        $this->call(DirectoryDefaultsSeeder::class);
        $this->call(AccessControlSeeder::class);

        $this->purgeExistingDemoData();

        // Wrapped so a failure partway through never leaves half-created demo
        // accounts behind — those would collide on unique email/slug on retry.
        DB::transaction(function (): void {
            $locations = $this->seedLocations();
            $agencies = $this->seedAgencies($locations);
            $profiles = $this->seedProfiles($locations, $agencies);

            foreach ($profiles as $profile) {
                $this->locationInventory->syncForProfile($profile);
            }
            // Kisumu is deliberately profile-less; still needs a sync to reflect zero.
            $this->locationInventory->sync($locations['kisumu']->id);

            $this->seedModerationAndVerification($profiles);
            $this->seedSearchActivity();
            $this->seedRedirectAndAlias($locations, $profiles);

            $this->printSummary();
        });
    }

    /**
     * Removes every demo account and everything it owns so the seeder can be
     * re-run cleanly. Profiles are deleted before their owning users because
     * profile_package_assignments.assigned_by and moderation_appeals.
     * appellant_user_id restrict-delete on users — deleting the profile first
     * lets those rows cascade away via profile_id before the user row goes.
     */
    private function purgeExistingDemoData(): void
    {
        // Not tied to a profile_id, so it survives profile deletion and would
        // otherwise accumulate one stale row per re-run.
        DirectoryRedirect::query()->where('reason', 'Demo slug change for testing redirect behaviour.')->delete();

        $demoUserIds = User::withTrashed()->where('email', 'like', 'demo-%')->pluck('id');
        if ($demoUserIds->isEmpty()) {
            return;
        }

        $profiles = Profile::withTrashed()
            ->where(fn ($q) => $q->whereIn('owner_user_id', $demoUserIds)
                ->orWhereHas('agency', fn ($q2) => $q2->whereIn('owner_user_id', $demoUserIds)))
            ->get();

        $disk = Storage::disk('profile_media');
        foreach ($profiles as $profile) {
            $disk->deleteDirectory($profile->public_id);
            $profile->forceDelete();
        }

        Agency::withTrashed()->whereIn('owner_user_id', $demoUserIds)->get()
            ->each(fn (Agency $agency) => $agency->forceDelete());

        User::withTrashed()->whereIn('id', $demoUserIds)->get()
            ->each(fn (User $user) => $user->forceDelete());
    }

    /** @return array<string, Location> */
    private function seedLocations(): array
    {
        $nairobi = Location::query()->firstOrCreate(
            ['slug' => 'nairobi', 'parent_id' => null],
            ['country_code' => 'KE', 'type' => 'city', 'name' => 'Nairobi', 'full_slug' => 'nairobi', 'status' => 'published'],
        );
        $this->ensureContent(
            $nairobi,
            'Nairobi Escorts',
            'Nairobi is East Africa\'s largest capital, and our busiest directory market — independent providers and agency talent list across every neighbourhood, from Westlands nightlife to the quieter Karen suburbs.',
            'Verified independent and agency escort listings across Nairobi, updated daily with new and returning providers.',
            'Nairobi splits into several distinct provider markets: Westlands and the CBD skew toward nightlife and business travel, Kilimani toward serviced-apartment regulars, and Karen toward a quieter, appointment-only clientele. Use the neighbourhood pages below to narrow your search to the area that matches what you\'re looking for.',
            "Q: How often are Nairobi listings updated?\nA: New and renewed profiles publish continuously; VIP and Premium placements refresh daily.\n\nQ: Which Nairobi neighbourhoods have the most listings?\nA: Westlands and Kilimani currently carry the largest concentration of active profiles, with Karen and the CBD close behind.",
        );

        $westlands = Location::query()->firstOrCreate(
            ['slug' => 'westlands', 'parent_id' => $nairobi->id],
            ['country_code' => 'KE', 'type' => 'neighbourhood', 'name' => 'Westlands', 'full_slug' => 'nairobi/westlands', 'status' => 'published'],
        );
        $this->ensureContent(
            $westlands,
            'Westlands Escorts',
            'Westlands is a vibrant, well-connected neighbourhood in Nairobi known for its nightlife, shopping malls, and central location.',
            'Independent and agency escort profiles active in Westlands, Nairobi\'s nightlife and shopping hub.',
            'Westlands draws a mixed crowd of business travellers and locals thanks to its concentration of hotels, malls, and late-night venues. Providers listed here typically serve both incall appointments near Westlands\' serviced apartments and outcall visits across nearby Nairobi.',
            "Q: Is Westlands a good area for incall appointments?\nA: Yes — Westlands has the highest concentration of serviced apartments in Nairobi, and most listed providers offer incall here.\n\nQ: Does a Westlands listing cover the CBD too?\nA: Providers based in Westlands often also serve the neighbouring CBD; check each profile's availability section for outcall range.",
        );

        $kilimani = Location::query()->firstOrCreate(
            ['slug' => 'kilimani', 'parent_id' => $nairobi->id],
            ['country_code' => 'KE', 'type' => 'neighbourhood', 'name' => 'Kilimani', 'full_slug' => 'nairobi/kilimani', 'status' => 'published'],
        );
        $this->ensureContent(
            $kilimani,
            'Kilimani Escorts',
            'Kilimani is a leafy, upscale Nairobi neighbourhood popular for its restaurants, serviced apartments, and central location.',
            'Escort listings active in Kilimani, an upscale Nairobi neighbourhood known for serviced apartments and restaurants.',
            'Kilimani\'s density of serviced apartments makes it one of Nairobi\'s most popular areas for discreet incall bookings. It borders Westlands and Kileleshwa, so providers here are also a convenient option if you\'re staying anywhere in central Nairobi.',
            null,
        );

        $karen = Location::query()->firstOrCreate(
            ['slug' => 'karen', 'parent_id' => $nairobi->id],
            ['country_code' => 'KE', 'type' => 'neighbourhood', 'name' => 'Karen', 'full_slug' => 'nairobi/karen', 'status' => 'published'],
        );
        $this->ensureContent(
            $karen,
            'Karen Escorts',
            'Karen is a quiet, green suburb on the edge of Nairobi National Park, known for its spacious properties and relaxed pace.',
            'Escort listings serving Karen, Nairobi\'s quiet, leafy suburb bordering Nairobi National Park.',
            'Karen sits apart from Nairobi\'s busier commercial districts, so most bookings here are outcall visits to private homes or guesthouses rather than hotel incall. Expect a smaller but more established roster of providers than in Westlands or Kilimani.',
            null,
        );

        // Deliberately under the indexability threshold until profiles are
        // assigned below — exercises locations.micro_min_profiles (6).
        $cbd = Location::query()->firstOrCreate(
            ['slug' => 'cbd', 'parent_id' => $westlands->id],
            ['country_code' => 'KE', 'type' => 'area', 'name' => 'CBD', 'full_slug' => 'nairobi/westlands/cbd-escorts', 'status' => 'published'],
        );
        $this->ensureContent(
            $cbd,
            'Westlands CBD Escorts',
            'The Westlands CBD area concentrates offices, hotels, and nightlife within easy reach of central Nairobi.',
            'Escort listings in the Westlands CBD micro-area, Nairobi\'s office and hotel district.',
            'Being wedged between Westlands\' malls and Nairobi\'s central business district, the CBD area is the most convenient option for same-day business-travel bookings — most listed providers here can accommodate short-notice hotel incall.',
            null,
        );

        $mombasa = Location::query()->firstOrCreate(
            ['slug' => 'mombasa', 'parent_id' => null],
            ['country_code' => 'KE', 'type' => 'city', 'name' => 'Mombasa', 'full_slug' => 'mombasa', 'status' => 'published'],
        );
        $this->ensureContent(
            $mombasa,
            'Mombasa Escorts',
            'Mombasa is Kenya\'s coastal port city — our second-largest market, anchored by the beach resorts and nightlife of Nyali.',
            'Verified escort listings across Mombasa, Kenya\'s coastal port city, including Nyali\'s resort district.',
            'Mombasa\'s provider base is smaller than Nairobi\'s but concentrated around the coast, so most listings are reachable from any Nyali or Mombasa Island hotel. Expect availability to lean toward evenings and weekends, in step with tourist and business travel patterns.',
            "Q: Do Mombasa providers travel to hotels outside Nyali?\nA: Most do — check each profile's outcall range, since some restrict travel to the Nyali/Mombasa Island corridor.",
        );

        $nyali = Location::query()->firstOrCreate(
            ['slug' => 'nyali', 'parent_id' => $mombasa->id],
            ['country_code' => 'KE', 'type' => 'neighbourhood', 'name' => 'Nyali', 'full_slug' => 'mombasa/nyali', 'status' => 'published'],
        );
        $this->ensureContent(
            $nyali,
            'Nyali Escorts',
            'Nyali is a beachfront Mombasa neighbourhood known for its resorts, malls, and relaxed coastal atmosphere.',
            'Escort listings in Nyali, Mombasa\'s beachfront resort neighbourhood.',
            'Nyali is Mombasa\'s tourist and resort core, so incall availability at beachfront hotels is common among listed providers. It\'s the natural starting point if you\'re visiting Mombasa and haven\'t booked accommodation elsewhere in the city.',
            null,
        );

        // Zero profiles on purpose: exercises the SEO orphan report and the
        // empty-location "nearby areas" suggestion.
        $kisumu = Location::query()->firstOrCreate(
            ['slug' => 'kisumu', 'parent_id' => null],
            ['country_code' => 'KE', 'type' => 'city', 'name' => 'Kisumu', 'full_slug' => 'kisumu', 'status' => 'published'],
        );
        $this->ensureContent(
            $kisumu,
            'Kisumu Escorts',
            'Kisumu, on the shore of Lake Victoria, is our newest market — check back soon as providers in the area come online.',
            'Escort listings for Kisumu, on the shore of Lake Victoria — new provider signups are opening in this market.',
            null,
            null,
        );

        return compact('nairobi', 'westlands', 'kilimani', 'karen', 'cbd', 'mombasa', 'nyali', 'kisumu');
    }

    private function ensureContent(
        Location $location,
        string $heading,
        string $intro,
        string $metaDescription,
        ?string $bottomContent,
        ?string $faq,
    ): void {
        LocationContent::query()->firstOrCreate(['location_id' => $location->id], [
            'heading' => $heading,
            'intro_content' => $intro,
            'bottom_content' => $bottomContent,
            'faq_content' => $faq ? ['content' => $faq] : null,
            'seo_title' => $heading.' | Directory Platform',
            'meta_description' => $metaDescription,
            'canonical_path' => '/'.$location->full_slug.(str_ends_with($location->full_slug, '-escorts') ? '' : '-escorts'),
            'content_status' => 'approved',
            'last_reviewed_at' => now(),
        ]);
    }

    /** @param  array<string, Location>  $locations
     * @return array<string, Agency> */
    private function seedAgencies(array $locations): array
    {
        $sunriseOwner = $this->demoUser('Sunrise Agency Owner', AccountType::Provider, ProviderType::Agency);
        $sunrise = Agency::query()->create([
            'owner_user_id' => $sunriseOwner->id,
            'name' => 'Sunrise Companions',
            'slug' => 'sunrise-companions',
            'description' => 'A Nairobi-based agency representing a small roster of verified independent providers.',
            'status' => 'active',
        ]);

        $velvetOwner = $this->demoUser('Velvet Collective Owner', AccountType::Provider, ProviderType::Agency);
        $velvet = Agency::query()->create([
            'owner_user_id' => $velvetOwner->id,
            'name' => 'Velvet Collective',
            'slug' => 'velvet-collective',
            'description' => 'A coastal agency covering Mombasa and Nyali.',
            'status' => 'active',
        ]);

        $this->summaryLines[] = "Agency owner (Sunrise Companions): {$sunriseOwner->email} / ".self::DEMO_PASSWORD;
        $this->summaryLines[] = "Agency owner (Velvet Collective): {$velvetOwner->email} / ".self::DEMO_PASSWORD;

        return compact('sunrise', 'velvet');
    }

    /**
     * @param  array<string, Location>  $locations
     * @param  array<string, Agency>  $agencies
     * @return Collection<int, Profile>
     */
    private function seedProfiles(array $locations, array $agencies): Collection
    {
        $profiles = collect();
        $genders = TaxonomyOption::query()->ofType('gender')->enabled()->get()->keyBy('slug');
        $builds = TaxonomyOption::query()->ofType('build')->enabled()->pluck('id')->all();
        $ethnicities = TaxonomyOption::query()->ofType('ethnicity')->enabled()->pluck('id')->all();
        $services = TaxonomyOption::query()->ofType('service')->enabled()->pluck('id')->all();
        $languages = TaxonomyOption::query()->ofType('language')->enabled()->pluck('id')->all();

        $taxonomyPool = compact('genders', 'builds', 'ethnicities', 'services', 'languages');

        // Independent, active, spread across packages and locations.
        $spread = [
            ['package' => 'vip', 'location' => $locations['westlands'], 'gender' => 'woman'],
            ['package' => 'vip', 'location' => $locations['kilimani'], 'gender' => 'trans-woman'],
            ['package' => 'vip', 'location' => $locations['nyali'], 'gender' => 'woman'],
            ['package' => 'premium', 'location' => $locations['westlands'], 'gender' => 'man'],
            ['package' => 'premium', 'location' => $locations['karen'], 'gender' => 'woman', 'new' => true],
            ['package' => 'premium', 'location' => $locations['nyali'], 'gender' => 'non-binary'],
            ['package' => 'basic', 'location' => $locations['westlands'], 'gender' => 'woman', 'skip_image' => true],
            ['package' => 'basic', 'location' => $locations['kilimani'], 'gender' => 'woman'],
            ['package' => 'basic', 'location' => $locations['karen'], 'gender' => 'man', 'new' => true],
            ['package' => 'basic', 'location' => $locations['nyali'], 'gender' => 'woman'],
        ];
        foreach ($spread as $i => $config) {
            $profiles->push($this->activeIndependentProfile($config, $taxonomyPool, $i));
        }

        // Six basic profiles under the CBD micro-location — crosses
        // locations.micro_min_profiles (6) so it becomes indexable.
        for ($i = 0; $i < 6; $i++) {
            $profiles->push($this->activeIndependentProfile([
                'package' => 'basic',
                'location' => $locations['westlands'],
                'micro' => $locations['cbd'],
                'gender' => $i % 2 === 0 ? 'woman' : 'man',
                'new' => $i === 0,
            ], $taxonomyPool, 100 + $i));
        }

        // Agency-owned profiles.
        foreach ([
            ['agency' => 'sunrise', 'package' => 'vip', 'location' => $locations['westlands'], 'gender' => 'woman'],
            ['agency' => 'sunrise', 'package' => 'premium', 'location' => $locations['kilimani'], 'gender' => 'woman'],
            ['agency' => 'sunrise', 'package' => 'basic', 'location' => $locations['karen'], 'gender' => 'man'],
            ['agency' => 'velvet', 'package' => 'vip', 'location' => $locations['nyali'], 'gender' => 'woman'],
            ['agency' => 'velvet', 'package' => 'premium', 'location' => $locations['nyali'], 'gender' => 'non-binary'],
        ] as $i => $config) {
            $agency = $agencies[$config['agency']];
            $config['assigned_by'] = $agency->owner_user_id;
            $profile = $this->activeIndependentProfile($config, $taxonomyPool, 200 + $i, ownerless: true);
            $agency->profiles()->attach($profile, ['assigned_by' => $agency->owner_user_id, 'assigned_at' => now()]);
            $profiles->push($profile);
        }

        // Edge-state profiles for staff tooling coverage.
        $draftOwner = $this->demoUser('Demo Draft Provider', AccountType::Provider, ProviderType::Independent);
        $draft = $this->baseProfile($draftOwner->id, 'Demo Draft Profile', $locations['westlands'], $taxonomyPool, 300, ProfileStatus::Draft);
        $profiles->push($draft);
        $this->summaryLines[] = "Draft profile owner: {$draftOwner->email} / ".self::DEMO_PASSWORD;

        $pendingOwner = $this->demoUser('Demo Pending Provider', AccountType::Provider, ProviderType::Independent);
        $pending = $this->baseProfile($pendingOwner->id, 'Demo Pending Review Profile', $locations['kilimani'], $taxonomyPool, 301, ProfileStatus::PendingReview);
        $pending->packageRequests()->create([
            'requested_package_id' => Package::query()->where('code', 'premium')->value('id'),
            'status' => PackageRequestStatus::Pending,
            'requested_by' => $pendingOwner->id,
            'requested_at' => now(),
        ]);
        $profiles->push($pending);
        $this->summaryLines[] = "Pending-review profile owner (staff queue): {$pendingOwner->email} / ".self::DEMO_PASSWORD;

        $rejectedOwner = $this->demoUser('Demo Rejected Provider', AccountType::Provider, ProviderType::Independent);
        $rejected = $this->baseProfile($rejectedOwner->id, 'Demo Rejected Profile', $locations['karen'], $taxonomyPool, 302, ProfileStatus::Rejected);
        $profiles->push($rejected);

        $expiredOwner = $this->demoUser('Demo Expired Provider', AccountType::Provider, ProviderType::Independent);
        $expired = $this->activeIndependentProfile(['package' => 'basic', 'location' => $locations['westlands'], 'gender' => 'woman', 'owner' => $expiredOwner], $taxonomyPool, 303);
        $expired->packageAssignments()->update(['status' => 'expired']);
        $expired->update(['status' => ProfileStatus::Expired, 'expires_at' => now()->subDays(3)]);
        $profiles->push($expired);
        $this->summaryLines[] = "Expired profile owner (renewal flow): {$expiredOwner->email} / ".self::DEMO_PASSWORD;

        return $profiles;
    }

    /** @param  array<string, mixed>  $taxonomyPool */
    private function activeIndependentProfile(array $config, array $taxonomyPool, int $seed, bool $ownerless = false): Profile
    {
        $owner = $ownerless ? null : ($config['owner'] ?? $this->demoUser('Demo Provider '.$seed, AccountType::Provider, ProviderType::Independent));
        $profile = $this->baseProfile($owner?->id, 'Demo Profile '.$seed, $config['location'], $taxonomyPool, $seed, ProfileStatus::Active, $config['micro'] ?? null);

        $package = Package::query()->where('code', $config['package'])->firstOrFail();
        $duration = PackageDurationOption::query()->where('duration_days', 30)->firstOrFail();
        $startsAt = ($config['new'] ?? false) ? now()->subDays(2) : now()->subDays(20);
        $profile->packageAssignments()->create([
            'package_id' => $package->id,
            'starts_at' => $startsAt,
            'expires_at' => $startsAt->copy()->addDays($duration->duration_days),
            'status' => 'active',
            'assigned_by' => $config['assigned_by'] ?? $owner->id,
            'assignment_source' => 'manual',
            'reason' => 'Demo data seed.',
        ]);
        $profile->update(['last_activated_at' => $startsAt, 'published_at' => $startsAt, 'expires_at' => $startsAt->copy()->addDays($duration->duration_days)]);

        $verificationTypes = ['adult_age', 'identity', 'publishing_rights'];
        if ($ownerless) {
            $verificationTypes[] = 'agency_authorization';
        }
        foreach ($verificationTypes as $type) {
            $profile->verificationChecks()->create([
                'check_type' => $type,
                'status' => 'verified',
                'evidence_reference' => 'DEMO-VERIFICATION-'.$seed.'-'.$type,
                'notes' => 'Synthetic verification evidence created only for non-production demo data.',
                'checked_at' => now(),
            ]);
        }
        $profile->update(['verification_status' => 'verified']);

        if (! ($config['skip_image'] ?? false)) {
            $this->attachDemoImage($profile, $seed);
        }

        return $profile;
    }

    /** @param  array<string, mixed>  $taxonomyPool */
    /**
     * $neighbourhood must be a neighbourhood-level Location (every call site
     * passes one) — primary_location_id is derived as its parent city, never
     * passed directly, so this can't be handed a city by mistake.
     */
    private function baseProfile(?int $ownerId, string $namePrefix, Location $neighbourhood, array $taxonomyPool, int $seed, ProfileStatus $status, ?Location $micro = null): Profile
    {
        $name = $this->faker->firstName().' '.$this->faker->lastName();
        $slug = Str::slug($name).'-'.$seed;
        $gender = $taxonomyPool['genders']->random();
        $requiresBust = $gender->settings['requires_bust_size'] ?? false;

        return tap(Profile::query()->create([
            'owner_user_id' => $ownerId,
            'display_name' => $name,
            'slug' => $slug,
            'description' => $this->faker->paragraphs(2, true),
            'primary_location_id' => $neighbourhood->parent_id,
            'sublocation_id' => $neighbourhood->id,
            'micro_location_id' => $micro?->id,
            'gender_option_id' => $gender->id,
            'date_of_birth' => now()->subYears($this->faker->numberBetween(19, 40))->subDays($this->faker->numberBetween(0, 300)),
            'ethnicity_option_id' => $this->faker->randomElement($taxonomyPool['ethnicities']),
            'build_option_id' => $this->faker->randomElement($taxonomyPool['builds']),
            'bust_size_option_id' => $requiresBust ? TaxonomyOption::query()->ofType('bust_size')->inRandomOrder()->value('id') : null,
            'allows_incall' => $this->faker->boolean(70),
            'allows_outcall' => $this->faker->boolean(70),
            'status' => $status,
        ]), function (Profile $profile) use ($taxonomyPool): void {
            $phone = '+2547'.$this->faker->numerify('########');
            $contacts = [
                ['type' => 'call', 'normalized_value' => $phone, 'display_value' => $phone, 'sort_order' => 10, 'is_public' => true],
                ['type' => 'sms', 'normalized_value' => $phone, 'display_value' => $phone, 'sort_order' => 20, 'is_public' => true],
            ];
            if ($this->faker->boolean(60)) {
                $contacts[] = ['type' => 'whatsapp', 'normalized_value' => $phone, 'display_value' => $phone, 'sort_order' => 30, 'is_public' => true];
            } elseif ($this->faker->boolean(50)) {
                $username = Str::lower($this->faker->userName());
                $contacts[] = ['type' => 'telegram_username', 'normalized_value' => $username, 'display_value' => '@'.$username, 'sort_order' => 40, 'is_public' => true];
            }
            $profile->contacts()->createMany($contacts);
            $profile->services()->sync(collect($taxonomyPool['services'])->random(min(3, count($taxonomyPool['services'])))->all());
            if ($this->faker->boolean(50)) {
                $profile->languages()->sync(collect($taxonomyPool['languages'])->random(min(2, count($taxonomyPool['languages'])))->all());
            }
        });
    }

    private function demoUser(string $name, AccountType $accountType, ?ProviderType $providerType): User
    {
        static $counter = 0;
        $counter++;
        $email = 'demo-'.Str::slug($name).'-'.$counter.'@example.test';

        return tap(User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'account_type' => $accountType,
            'provider_type' => $providerType,
            'onboarding_status' => OnboardingStatus::Completed,
            'onboarding_completed_at' => now(),
            'last_seen_at' => now()->subMinutes($this->faker->numberBetween(1, 500)),
        ]), fn (User $user) => $user->forceFill(['email_verified_at' => now()])->save());
    }

    private function attachDemoImage(Profile $profile, int $seed): void
    {
        $colors = [[233, 196, 106], [244, 162, 97], [231, 111, 81], [42, 157, 143], [38, 70, 83], [138, 108, 191]];
        [$r, $g, $b] = $colors[$seed % count($colors)];

        $source = imagecreatetruecolor(800, 1000);
        imagefill($source, 0, 0, imagecolorallocate($source, $r, $g, $b));

        $directory = $profile->public_id;
        $disk = Storage::disk('profile_media');
        $disk->makeDirectory($directory);
        $slots = ['thumb' => 320, 'card' => 640, 'profile' => 960, 'full' => 1280];
        $derivatives = [];
        foreach ($slots as $slot => $maxWidth) {
            $width = min($maxWidth, 800);
            $height = (int) round(1000 * ($width / 800));
            $derivative = imagecreatetruecolor($width, $height);
            imagecopyresampled($derivative, $source, 0, 0, 0, 0, $width, $height, 800, 1000);
            $filename = $slot.'-'.$maxWidth.'.webp';
            imagewebp($derivative, $disk->path($directory.'/'.$filename), 80);
            imagedestroy($derivative);
            $derivatives[$slot] = ['width' => $width, 'height' => $height, 'file' => $filename];
        }
        imagedestroy($source);

        ProfileImage::query()->create([
            'profile_id' => $profile->id,
            'storage_directory' => $directory,
            'sort_order' => 10,
            'status' => 'approved',
            'width' => 800,
            'height' => 1000,
            'aspect_ratio' => 0.8,
            'mime_type' => 'image/webp',
            'file_size' => 1000,
            'exact_hash' => hash('sha256', $directory),
            'derivatives' => $derivatives,
        ]);
    }

    /** @param  Collection<int, Profile>  $profiles */
    private function seedModerationAndVerification(Collection $profiles): void
    {
        $activeProfiles = $profiles->filter(fn (Profile $p) => $p->status === ProfileStatus::Active)->values();
        $staff = User::query()->whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'csr']))->first()
            ?? $this->demoUser('Demo Staff Reviewer', AccountType::Member, null);

        // A normal report, still open.
        ProfileReport::query()->create([
            'profile_id' => $activeProfiles[0]->id,
            'reporter_email' => 'concerned-visitor@example.test',
            'reporter_email_hash' => hash('sha256', 'concerned-visitor@example.test'),
            'category' => 'inaccurate_listing',
            'details' => 'Demo report: the listed services do not match what was offered.',
            'priority' => 'normal',
            'status' => 'new',
        ]);

        // An urgent report, so priority sorting has something to sort.
        ProfileReport::query()->create([
            'profile_id' => $activeProfiles[1]->id,
            'reporter_email' => 'urgent-reporter@example.test',
            'reporter_email_hash' => hash('sha256', 'urgent-reporter@example.test'),
            'category' => 'suspected_minor',
            'details' => 'Demo urgent report for testing priority ordering in the moderation queue.',
            'priority' => 'urgent',
            'status' => 'new',
        ]);

        // A profile taken private via moderation, with a pending appeal.
        $deactivated = $activeProfiles[2];
        $this->enforcement->makePrivate($deactivated);
        $action = ModerationAction::query()->create([
            'profile_id' => $deactivated->id,
            'actor_user_id' => $staff->id,
            'action' => 'make_private',
            'previous_profile_status' => ProfileStatus::Active->value,
            'new_profile_status' => ProfileStatus::Deactivated->value,
            'reason' => 'Demo moderation hold pending evidence review.',
        ]);
        ModerationAppeal::query()->create([
            'profile_id' => $deactivated->id,
            'moderation_action_id' => $action->id,
            'appellant_user_id' => $deactivated->owner_user_id ?? $staff->id,
            'reason' => 'Demo appeal: requesting the restriction be reviewed with supporting evidence.',
            'status' => 'pending',
        ]);
        $this->summaryLines[] = "Deactivated profile with a pending appeal: {$deactivated->slug} (staff.moderation queue)";

        // A banned profile, via the real emergency-takedown-equivalent path.
        $banned = $activeProfiles[3];
        $this->enforcement->ban($banned);
        ModerationAction::query()->create([
            'profile_id' => $banned->id,
            'actor_user_id' => $staff->id,
            'action' => 'ban',
            'previous_profile_status' => ProfileStatus::Active->value,
            'new_profile_status' => ProfileStatus::Banned->value,
            'reason' => 'Demo ban for testing the private-listing staff view.',
        ]);
        $this->summaryLines[] = "Banned profile: {$banned->slug}";

        // Verification checks across every type and status.
        foreach ([
            ['type' => 'adult_age', 'status' => 'verified'],
            ['type' => 'identity', 'status' => 'pending'],
            ['type' => 'publishing_rights', 'status' => 'rejected'],
        ] as $i => $check) {
            VerificationCheck::query()->create([
                'profile_id' => $activeProfiles[4 + $i]->id,
                'check_type' => $check['type'],
                'status' => $check['status'],
                'performed_by' => $staff->id,
                'evidence_reference' => 'demo-evidence-ref-'.($i + 1),
                'notes' => 'Demo verification check seeded for testing the verification queue.',
            ]);
        }
    }

    private function seedSearchActivity(): void
    {
        // Crosses the >10/day threshold so it actually gets logged and shows
        // up on /seo/search-insights.
        for ($i = 0; $i < 14; $i++) {
            $this->searchLogger->record('nairobi massage');
        }
        for ($i = 0; $i < 11; $i++) {
            $this->searchLogger->record('vip escort');
        }
        // Below the threshold on purpose — should never appear in insights.
        $this->searchLogger->record('a rare one-off search term');
    }

    /** @param  array<string, Location>  $locations
     * @param  Collection<int, Profile>  $profiles */
    private function seedRedirectAndAlias(array $locations, Collection $profiles): void
    {
        // Alternate spelling that should 301 to the canonical Nairobi page.
        LocationAlias::query()->firstOrCreate(
            ['location_id' => $locations['nairobi']->id, 'normalized_alias' => 'nrb'],
            ['alias' => 'NRB'],
        );

        // A changed profile slug with permanent history, demonstrating the
        // old-URL-redirects-forever behaviour.
        $renamed = $profiles->first(fn (Profile $p) => $p->status === ProfileStatus::Active);
        if ($renamed) {
            $oldSlug = $renamed->slug;
            $newSlug = $oldSlug.'-renamed';
            ProfileSlugHistory::query()->create([
                'profile_id' => $renamed->id,
                'old_slug' => $oldSlug,
                'new_slug' => $newSlug,
                'changed_at' => now(),
            ]);
            DirectoryRedirect::query()->create([
                'source_path' => '/escort/'.$oldSlug,
                'target_path' => '/escort/'.$newSlug,
                'status_code' => 301,
                'reason' => 'Demo slug change for testing redirect behaviour.',
                'is_active' => true,
            ]);
            $renamed->update(['slug' => $newSlug]);
            $this->summaryLines[] = "Redirect demo: /escort/{$oldSlug} -> /escort/{$newSlug}";
        }
    }

    private function printSummary(): void
    {
        $this->command?->info('Demo data seeded.');
        foreach ($this->summaryLines as $line) {
            $this->command?->line(' - '.$line);
        }
        $this->command?->line(' - Every demo account password: '.self::DEMO_PASSWORD);
    }
}
