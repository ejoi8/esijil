<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * High-volume, realistic demo seeder built on bulk native inserts (no Eloquent,
 * no factories) so it can produce ~1M rows in minutes.
 *
 * It covers every real-life event situation:
 *   - registration-only events
 *   - registration + attendance (scanning)
 *   - registration + attendance + certificate
 *   - registration + certificate (instant, no scanning)
 * and within scanning, both match modes: platform QR (participant.public_token)
 * and external ID (participant.external_id, e.g. IC / staff card). Every event
 * also gets 1-3 per-event registration custom fields (varied types incl. file),
 * with public values filled into each registration's `details`.
 *
 * Strategy: truncate the tenant tables (resets auto-increment to 1), then insert
 * with EXPLICIT sequential ids so foreign keys can be wired without round-trips.
 * The `users` table is intentionally NOT truncated; a demo platform-admin is
 * created instead and added to every demo org so the data is viewable.
 */
class SeedDemoData extends Command
{
    protected $signature = 'esijil:seed-demo
        {--organizations=100 : Number of organizations}
        {--events=2000 : Total number of events (spread across organizations)}
        {--registrations=1000000 : Approximate total number of registrations}
        {--participants=300000 : Size of the participant pool (reused across events)}
        {--chunk=2000 : Max rows per bulk insert (auto-clamped to the bind-param limit)}
        {--force : Skip the destructive-truncate confirmation prompt}';

    protected $description = 'Seed realistic high-volume demo data (orgs, events, participants, registrations, attendance, certificates) via bulk native inserts. TRUNCATES tenant tables first.';

    /** Memoized bcrypt hash of the demo station PIN ("123456"), reused across stations. */
    private ?string $demoPinHash = null;

    /** Tenant tables wiped before seeding. `users` is deliberately excluded. Child-first order. */
    private const TENANT_TABLES = [
        'registrations', 'scanner_stations', 'custom_fields',
        'events', 'participants', 'certificate_templates',
        'organization_user', 'organizations',
    ];

    private const DEMO_ADMIN_EMAIL = 'admin@admin.com';

    private const FIRST_NAMES = ['Ahmad', 'Nurul', 'Muhammad', 'Siti', 'Mohd', 'Aisyah', 'Faizal', 'Farah', 'Hafiz', 'Aina', 'Zulkifli', 'Liyana', 'Iskandar', 'Maryam', 'Danish', 'Sofea', 'Haziq', 'Balqis', 'Amir', 'Wan'];

    private const LAST_NAMES = ['Abdullah', 'Ismail', 'Rahman', 'Yusof', 'Hassan', 'Othman', 'Ibrahim', 'Karim', 'Salleh', 'Aziz', 'Bakar', 'Razak', 'Tan', 'Lim', 'Subramaniam', 'Kaur', 'Wong', 'Chong', 'Ramli', 'Daud'];

    private const VENUES = ['Dewan Besar', 'Auditorium Utama', 'Pusat Konvensyen', 'Bilik Seminar A', 'Padang Perhimpunan', 'Hotel Grand', 'Kompleks Sukan', 'Balai Islam'];

    /**
     * Pool of per-event registration custom fields (entity=registration). Each
     * event gets a random 1-3 of these — varied types including a file upload.
     * Public/both-scope values are filled into each registration's `details`,
     * exactly as a real public submission would. `options` is a value=>label map.
     *
     * @var list<array<string, mixed>>
     */
    private const CUSTOM_FIELD_POOL = [
        ['key' => 'baju', 'label' => 'Saiz Baju', 'type' => 'select', 'scope' => 'public', 'options' => ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL', 'XXL' => 'XXL']],
        ['key' => 'sesi', 'label' => 'Sesi Pilihan', 'type' => 'select', 'scope' => 'public', 'options' => ['pagi' => 'Sesi Pagi', 'petang' => 'Sesi Petang']],
        ['key' => 'diet', 'label' => 'Keperluan Pemakanan', 'type' => 'text', 'scope' => 'public'],
        ['key' => 'jawatan', 'label' => 'Jawatan', 'type' => 'text', 'scope' => 'both'],
        ['key' => 'umur', 'label' => 'Umur', 'type' => 'number', 'scope' => 'public'],
        ['key' => 'tarikh_lahir', 'label' => 'Tarikh Lahir', 'type' => 'date', 'scope' => 'public'],
        ['key' => 'penginapan', 'label' => 'Perlukan Penginapan?', 'type' => 'checkbox', 'scope' => 'public'],
        ['key' => 'catatan', 'label' => 'Catatan Tambahan', 'type' => 'textarea', 'scope' => 'public'],
        ['key' => 'ic_copy', 'label' => 'Salinan Kad Pengenalan', 'type' => 'file', 'scope' => 'public', 'max_file_kb' => 2048, 'accepted' => ['pdf', 'jpg', 'png']],
    ];

    public function handle(): int
    {
        $orgs   = max(1, (int) $this->option('organizations'));
        $events = max($orgs, (int) $this->option('events'));
        $regs   = max(0, (int) $this->option('registrations'));
        $pool   = max($orgs, (int) $this->option('participants'));
        $chunk  = max(500, (int) $this->option('chunk'));

        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $this->warn('This TRUNCATES: '.implode(', ', self::TENANT_TABLES).' (your `users` rows are preserved).');
        $this->line(sprintf(
            'Then seeds ~%s orgs, %s events, ~%s registrations from a pool of %s participants.',
            number_format($orgs), number_format($events), number_format($regs), number_format($pool)
        ));

        if (! $this->option('force') && ! $this->confirm('Proceed? This destroys existing tenant data.')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        DB::connection()->disableQueryLog();
        $started = microtime(true);

        $this->truncateTenantTables();
        $adminId = $this->seedDemoAdmin();

        // --- plan the distribution up front so ids/budgets are deterministic ---
        $eventsPerOrg = $this->distribute($events, $orgs);
        $poolPerOrg   = $this->distribute($pool, $orgs);
        $regsPerOrg   = $this->distributeWeighted($regs, $eventsPerOrg); // bigger orgs get more regs

        // running global id counters (auto-increment was reset by truncate)
        $eventId = $stationId = $partId = $regId = $customFieldId = 0;
        $serialSeq = 0;
        $now = date('Y-m-d H:i:s');
        $nowTs = time();

        $counts = ['organizations' => 0, 'certificate_templates' => 0, 'organization_user' => 0,
            'events' => 0, 'custom_fields' => 0, 'scanner_stations' => 0, 'participants' => 0, 'registrations' => 0];

        $bar = $this->output->createProgressBar($orgs);
        $bar->start();

        for ($o = 1; $o <= $orgs; $o++) {
            DB::beginTransaction();

            // org + its certificate template (id == org id) + admin membership
            DB::table('organizations')->insert([
                'id' => $o, 'name' => "Demo Organization {$o}", 'slug' => "demo-org-{$o}",
                'locale' => 'ms', 'settings' => null, 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('certificate_templates')->insert([
                'id' => $o, 'organization_id' => $o, 'name' => "Sijil Rasmi Org {$o}",
                'key' => "demo-cert-org-{$o}", 'schema' => null, 'pdfme_template' => null,
                'is_active' => 1, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            DB::table('organization_user')->insert([
                'id' => $o, 'organization_id' => $o, 'user_id' => $adminId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $counts['organizations']++;
            $counts['certificate_templates']++;
            $counts['organization_user']++;

            // ---- participants for this org ----
            $poolStart = $partId + 1;
            $poolCount = $poolPerOrg[$o - 1];
            $participantRows = [];
            for ($i = 0; $i < $poolCount; $i++) {
                $partId++;
                $participantRows[] = [
                    'id' => $partId,
                    'organization_id' => $o,
                    'public_token' => 'DEMO'.str_pad((string) $partId, 22, '0', STR_PAD_LEFT),
                    'external_id' => 'STF'.str_pad((string) $partId, 12, '0', STR_PAD_LEFT),
                    'full_name' => self::FIRST_NAMES[$partId % count(self::FIRST_NAMES)].' '.self::LAST_NAMES[($partId >> 2) % count(self::LAST_NAMES)],
                    'email' => "peserta{$partId}@example.test",
                    'phone' => '01'.str_pad((string) ($partId % 100000000), 8, '0', STR_PAD_LEFT),
                    'details' => null,
                    'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
                ];
            }
            $this->bulkInsert('participants', $participantRows);
            $counts['participants'] += $poolCount;
            $participantRows = null;

            // ---- events for this org (with stations) ----
            $orgEvents = [];                 // event meta used by the registration phase
            $orgEventCount = $eventsPerOrg[$o - 1];
            $eventRows = [];
            $stationRows = [];
            $customFieldRows = [];
            for ($e = 0; $e < $orgEventCount; $e++) {
                $eventId++;
                $scenario = $this->pickScenario();        // reg | att | full | regcert
                $time = $this->pickTime();                 // past | future | draft
                $hasAttendance = in_array($scenario, ['att', 'full'], true);
                $hasCertificate = in_array($scenario, ['full', 'regcert'], true);
                $isPast = $time === 'past';

                [$startsTs, $endsTs] = $this->eventWindow($time, $nowTs);
                $scanMode = $hasAttendance ? (mt_rand(1, 100) <= 60 ? 'token' : 'external_id') : 'token';
                $modules = match ($scenario) {
                    'reg' => ['registration'],
                    'att' => ['registration', 'attendance'],
                    'full' => ['registration', 'attendance', 'certificate'],
                    'regcert' => ['registration', 'certificate'],
                };
                $release = $scenario === 'full' ? 'after_checkin' : ($scenario === 'regcert' ? 'immediate' : 'immediate');
                $status = $time === 'past' ? 'completed' : ($time === 'future' ? 'published' : 'draft');

                $stationIds = [];
                if ($hasAttendance) {
                    foreach (range(1, mt_rand(1, 3)) as $k) {
                        $stationId++;
                        $stationIds[] = $stationId;
                        $stationRows[] = [
                            'id' => $stationId, 'organization_id' => $o, 'event_id' => $eventId,
                            'token' => 'STN'.str_pad((string) $stationId, 37, '0', STR_PAD_LEFT),
                            'pin' => $this->demoPinHash ??= Hash::make('123456'),
                            'label' => "Pintu {$k}", 'active' => 1, 'expires_at' => null,
                            'created_at' => $now, 'updated_at' => $now,
                        ];
                    }
                }

                // 1-3 per-event registration custom fields (varied types).
                $pool = self::CUSTOM_FIELD_POOL;
                shuffle($pool);
                $publicFields = [];
                $sort = 0;
                foreach (array_slice($pool, 0, mt_rand(1, 3)) as $def) {
                    $customFieldId++;
                    $customFieldRows[] = [
                        'id' => $customFieldId, 'organization_id' => $o, 'entity' => 'registration',
                        'event_id' => $eventId, 'key' => $def['key'], 'label' => $def['label'],
                        'type' => $def['type'],
                        'options' => isset($def['options']) ? json_encode($def['options']) : null,
                        'max_file_kb' => $def['max_file_kb'] ?? null,
                        'accepted_file_types' => isset($def['accepted']) ? json_encode($def['accepted']) : null,
                        'required' => 0, 'scope' => $def['scope'], 'help_text' => null,
                        'cert_var' => null, 'sort' => ++$sort, 'active' => 1,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                    if ($def['scope'] !== 'admin') {
                        $publicFields[] = $def;
                    }
                }

                $eventRows[] = [
                    'id' => $eventId, 'organization_id' => $o,
                    'public_id' => 'DEMOEVT'.str_pad((string) $eventId, 15, '0', STR_PAD_LEFT),
                    'legacy_id' => null,
                    'title' => "Program Demo {$eventId} (".strtoupper($scenario).')',
                    'description' => 'Acara demo yang dijana secara automatik.',
                    'details' => null,
                    'starts_at' => date('Y-m-d H:i:s', $startsTs),
                    'ends_at' => date('Y-m-d H:i:s', $endsTs),
                    'start_time_text' => null, 'end_time_text' => null,
                    'venue' => self::VENUES[$eventId % count(self::VENUES)],
                    'organizer_name' => "Demo Organization {$o}",
                    'registration_open' => $time === 'future' ? 1 : 0,
                    'status' => $status,
                    'certificate_template_id' => $hasCertificate ? $o : null,
                    'modules' => json_encode($modules),
                    'scan_match_mode' => $scanMode,
                    'certificate_release' => $release,
                    'created_by' => $adminId,
                    'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
                ];

                $orgEvents[] = [
                    'id' => $eventId, 'startsTs' => $startsTs, 'endsTs' => $endsTs,
                    'attendance' => $hasAttendance && $isPast, 'certificate' => $hasCertificate,
                    'release' => $release, 'isPast' => $isPast, 'draft' => $time === 'draft',
                    'certTplId' => $hasCertificate ? $o : null, 'stationIds' => $stationIds,
                    'publicFields' => $publicFields,
                ];
            }
            $this->bulkInsert('events', $eventRows);
            $this->bulkInsert('scanner_stations', $stationRows);
            $this->bulkInsert('custom_fields', $customFieldRows);
            $counts['events'] += $orgEventCount;
            $counts['custom_fields'] += count($customFieldRows);
            $counts['scanner_stations'] += count($stationRows);
            $eventRows = $stationRows = $customFieldRows = null;

            // ---- registrations for this org ----
            $orgRegBudget = $regsPerOrg[$o - 1];
            $perEvent = $this->splitBudgetAcrossEvents($orgRegBudget, $orgEvents, $poolCount);
            $poolIdx = range($poolStart, $poolStart + $poolCount - 1);
            $regBuffer = [];

            foreach ($orgEvents as $idx => $ev) {
                $n = $perEvent[$idx];
                if ($n <= 0) {
                    continue;
                }
                shuffle($poolIdx);
                for ($i = 0; $i < $n; $i++) {
                    $regId++;
                    $pid = $poolIdx[$i];
                    [$row, $serialSeq] = $this->registrationRow($regId, $o, $ev, $pid, $nowTs, $now, $serialSeq);
                    $regBuffer[] = $row;
                    if (count($regBuffer) >= 3000) {
                        $this->bulkInsert('registrations', $regBuffer);
                        $counts['registrations'] += count($regBuffer);
                        $regBuffer = [];
                    }
                }
            }
            if ($regBuffer) {
                $this->bulkInsert('registrations', $regBuffer);
                $counts['registrations'] += count($regBuffer);
            }

            DB::commit();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->resyncAutoIncrement([
            'organizations' => $orgs + 1, 'certificate_templates' => $orgs + 1,
            'organization_user' => $orgs + 1, 'events' => $eventId + 1,
            'custom_fields' => $customFieldId + 1,
            'scanner_stations' => $stationId + 1, 'participants' => $partId + 1,
            'registrations' => $regId + 1,
        ]);

        $this->table(['Table', 'Rows inserted'], collect($counts)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());
        $this->info(sprintf('Done in %.1fs. Login: %s / password', microtime(true) - $started, self::DEMO_ADMIN_EMAIL));

        return self::SUCCESS;
    }

    private function truncateTenantTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TENANT_TABLES as $table) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();
        $this->line('Truncated '.count(self::TENANT_TABLES).' tenant tables.');
    }

    private function seedDemoAdmin(): int
    {
        DB::table('users')->where('email', self::DEMO_ADMIN_EMAIL)->delete();

        return (int) DB::table('users')->insertGetId([
            'name' => 'Super Admin',
            'email' => self::DEMO_ADMIN_EMAIL,
            'is_platform_admin' => 1,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'password' => Hash::make('password'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Build one registration row honouring the event's lifecycle/scenario. */
    private function registrationRow(int $regId, int $orgId, array $ev, int $pid, int $nowTs, string $now, int $serialSeq): array
    {
        $registeredTs = $ev['isPast']
            ? $ev['startsTs'] - mt_rand(1, 30) * 86400
            : $nowTs - mt_rand(0, 25) * 86400;

        $status = 'registered';
        $checkedInAt = null;
        $stationId = null;
        $completedAt = null;

        if ($ev['attendance']) {
            $roll = mt_rand(1, 100);
            if ($roll <= 70) {                       // attended (checked in)
                $status = 'attended';
                $checkedInTs = $ev['startsTs'] + mt_rand(0, max(1, $ev['endsTs'] - $ev['startsTs']));
                $checkedInAt = date('Y-m-d H:i:s', $checkedInTs);
                $completedAt = $checkedInAt;
                $stationId = $ev['stationIds'][array_rand($ev['stationIds'])];
            } elseif ($roll <= 82) {                 // explicit no-show
                $status = 'no_show';
            }
            // else: registered but never scanned
        }

        $certTplId = $ev['certTplId'];
        $serial = null;
        $certIssuedAt = null;
        $certMeta = null;
        if ($ev['certificate'] && $ev['isPast']) {
            $eligible = $ev['release'] === 'after_checkin' ? $status === 'attended' : true;
            if ($eligible && mt_rand(1, 100) <= 80) {
                $serialSeq++;
                $serial = 'CERT-'.date('Y', $ev['startsTs']).'-'.str_pad((string) $serialSeq, 9, '0', STR_PAD_LEFT);
                $issuedTs = ($checkedInAt ? strtotime($checkedInAt) : $registeredTs) + mt_rand(0, 3) * 86400;
                $certIssuedAt = date('Y-m-d H:i:s', min($issuedTs, $nowTs));
                $certMeta = json_encode(['source' => 'seed']);
            }
        }

        $source = match (true) {
            ($r = mt_rand(1, 100)) <= 65 => 'public_form',
            $r <= 90 => 'import',
            default => 'admin',
        };

        $registeredAt = date('Y-m-d H:i:s', $registeredTs);

        return [[
            'id' => $regId, 'organization_id' => $orgId, 'legacy_id' => null,
            'event_id' => $ev['id'], 'participant_id' => $pid,
            'registered_at' => $registeredAt, 'attendance_status' => $status,
            'checked_in_at' => $checkedInAt, 'checked_in_station_id' => $stationId,
            'completed_at' => $completedAt, 'source' => $source, 'remarks' => null,
            'details' => $this->registrationDetails($ev['publicFields'] ?? []),
            'certificate_template_id' => $certTplId, 'cert_serial_number' => $serial,
            'certificate_issued_at' => $certIssuedAt, 'certificate_metadata' => $certMeta,
            'created_at' => $registeredAt, 'updated_at' => $checkedInAt ?? $registeredAt, 'deleted_at' => null,
        ], $serialSeq];
    }

    /**
     * Build a registration's `details` JSON from its event's public custom
     * fields, mimicking a real public submission. Null when there are none.
     */
    private function registrationDetails(array $publicFields): ?string
    {
        if ($publicFields === []) {
            return null;
        }

        $details = [];
        foreach ($publicFields as $def) {
            $details[$def['key']] = $this->sampleFieldValue($def);
        }

        return json_encode($details);
    }

    /** A plausible stored value for a custom field, by type/key. */
    private function sampleFieldValue(array $def): mixed
    {
        return match ($def['type']) {
            'select' => array_rand($def['options']),
            'checkbox' => mt_rand(0, 1) === 1,
            'number' => (string) mt_rand(18, 65),
            'date' => date('Y-m-d', time() - mt_rand(7000, 20000) * 86400),
            'file' => 'custom-fields/demo-placeholder.pdf',
            'textarea' => 'Catatan demo yang dijana secara automatik.',
            'email' => 'peserta@example.test',
            default => match ($def['key']) {
                'diet' => ['Vegetarian', 'Halal', 'Vegan', 'Tiada'][mt_rand(0, 3)],
                'jawatan' => ['Ahli', 'Setiausaha', 'Bendahari', 'Pengerusi', 'AJK'][mt_rand(0, 4)],
                default => 'Demo',
            },
        };
    }

    private function pickScenario(): string
    {
        $r = mt_rand(1, 100);

        return match (true) {
            $r <= 40 => 'reg',       // registration only
            $r <= 70 => 'att',       // registration + attendance
            $r <= 90 => 'full',      // registration + attendance + certificate
            default => 'regcert',    // registration + certificate (no scanning)
        };
    }

    private function pickTime(): string
    {
        $r = mt_rand(1, 100);

        return match (true) {
            $r <= 70 => 'past',
            $r <= 92 => 'future',
            default => 'draft',
        };
    }

    /** @return array{0:int,1:int} [startsTs, endsTs] */
    private function eventWindow(string $time, int $nowTs): array
    {
        $startsTs = match ($time) {
            'past' => $nowTs - mt_rand(1, 365) * 86400,
            'future' => $nowTs + mt_rand(1, 120) * 86400,
            default => $nowTs + mt_rand(30, 200) * 86400,
        };
        // land it on a sensible hour and give it a 2-8h duration
        $startsTs = $startsTs - ($startsTs % 86400) + mt_rand(8, 16) * 3600;

        return [$startsTs, $startsTs + mt_rand(2, 8) * 3600];
    }

    /** Distribute $total across $events by random weight; draft events get 0; cap by pool. */
    private function splitBudgetAcrossEvents(int $total, array $events, int $poolCount): array
    {
        $weights = [];
        $sum = 0.0;
        foreach ($events as $i => $ev) {
            $w = $ev['draft'] ? 0.0 : mt_rand(30, 180) / 100;
            $weights[$i] = $w;
            $sum += $w;
        }
        $out = [];
        $assigned = 0;
        foreach ($events as $i => $ev) {
            $n = $sum > 0 ? (int) round($total * $weights[$i] / $sum) : 0;
            $n = min($n, $poolCount); // distinct sampling can't exceed the pool
            $out[$i] = $n;
            $assigned += $n;
        }

        return $out;
    }

    /** Split $total into $n buckets that sum exactly to $total (remainder spread over the first buckets). */
    private function distribute(int $total, int $n): array
    {
        $base = intdiv($total, $n);
        $rem = $total - $base * $n;

        return array_map(fn ($i) => $base + ($i < $rem ? 1 : 0), range(0, $n - 1));
    }

    /** Split $total proportionally to $weights (sums exactly to $total). */
    private function distributeWeighted(int $total, array $weights): array
    {
        $sum = array_sum($weights) ?: 1;
        $out = [];
        $assigned = 0;
        foreach ($weights as $w) {
            $n = (int) floor($total * $w / $sum);
            $out[] = $n;
            $assigned += $n;
        }
        // hand any rounding remainder to the first bucket
        if ($out) {
            $out[0] += $total - $assigned;
        }

        return $out;
    }

    /** Bulk insert, auto-splitting to stay under MySQL's ~65k bind-parameter limit. */
    private function bulkInsert(string $table, array $rows): void
    {
        if (! $rows) {
            return;
        }
        $cols = count($rows[0]);
        $maxRows = max(1, intdiv(60000, $cols));
        foreach (array_chunk($rows, $maxRows) as $batch) {
            DB::table($table)->insert($batch);
        }
    }

    private function resyncAutoIncrement(array $nextIds): void
    {
        // Explicit ids leave the AUTO_INCREMENT counter behind on MySQL; nudge it
        // forward so the app's next insert doesn't collide. MySQL-only syntax.
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach ($nextIds as $table => $next) {
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = ".(int) $next);
        }
    }
}
