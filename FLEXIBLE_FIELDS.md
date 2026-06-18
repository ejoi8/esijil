# eSIJIL — Flexible Participant Fields: Design & Recommendation

*Laravel 13.6 · Filament 5.4 · Livewire 4 · MySQL 8.4 (Laragon). Citations verified against the current codebase, 2026-06-18.*

> ## ✅ Implemented (2026-06-18): dashboard-managed custom fields (Approach B)
> The project moved past the config-file approach (Approach A) described below to a **runtime, admin-managed** system. Fields are now defined in the dashboard, not in code:
> - **`custom_fields` table + `App\Models\CustomField`** — one row per field (entity, key, label, type, options, required, scope, sort, active, `cert_var`). Managed via **`CustomFieldResource`** (Settings → Custom Fields, admin-only).
> - **`App\Fields\CustomFields`** — entity-aware accessor (replacing the old `ParticipantFields`). Drives the admin form/table/infolist, the public registration form, validation (derived from type + required), and certificate variables.
> - Works for **both `participant` and `registration`** records; values live in each model's `details` JSON column. The public form namespaces inputs (`participant_details[…]` / `registration_details[…]`).
>
> The sections below are retained as the original design rationale (the config-file `ParticipantFields`/`config/participant_fields.php` they reference no longer exist).

> **Question this answers:** the participant model is rigid — a field like "Status Ahli" (`membership_status`) is a fixed column wired into many files, so adding/removing a field is painful. How do we make fields flexible to add/remove **without over-complicating things**?

---

## 1. The rigidity problem, grounded in the real code

Today, a single participant attribute like `membership_status` is hard-wired in **eight separate places**. Adding "Jawatan" (position) or "Saiz Baju" (shirt size) means editing all eight, in lockstep, in one deploy:

| # | File | Line(s) | What is hard-coded |
|---|------|---------|--------------------|
| 1 | `app/Models/Participant.php` | `#[Fillable]`, `casts()` | `membership_status` in fillable; cast to `MembershipStatus::class` |
| 2 | `database/migrations/2026_03_24_235955_create_participants_table.php` | 21–22 | physical columns `membership_status`, `membership_notes` |
| 3 | `app/Filament/Resources/Participants/Schemas/ParticipantForm.php` | 39–44 | `Select::make('membership_status')->options(MembershipStatus::options())` + `Textarea` |
| 4 | `app/Filament/Resources/Participants/Schemas/ParticipantInfolist.php` | 24 | `TextEntry::make('membership_status')` |
| 5 | `app/Filament/Resources/Participants/Tables/ParticipantsTable.php` | 38–41 (column), 57–60 (filter) | badge column + `SelectFilter` |
| 6 | `app/Http/Requests/StoreEventRegistrationRequest.php` | rules + `participantData()` | `Rule::in(MembershipStatus::values())` + payload mapping |
| 7 | `resources/views/event-registrations/show.blade.php` | the public `<select name="membership_status">` | hand-written options "Ahli"/"Bukan Ahli" |
| 8 | `database/factories/ParticipantFactory.php` (+ `LegacyEsijilSeeder::mapMembershipStatus()`) | 27 | factory/seeder values |

That is the pain: **8 files, every time, forever.** The goal is to make adding/removing a field cheap and safe — without trading the simplicity you value for an architecture nobody wants to maintain.

---

## 2. The option spectrum (simplest → most complex)

| Option | What it is | When to use / Why not |
|--------|-----------|----------------------|
| **(i) Keep fixed columns** | Status quo: a real DB column per field, wired in 8 places. | **Use** if fields change once or twice a year. Fully indexed, type-safe, reportable, every Laravel dev understands it. **Why not:** the 8-edit tax per field is exactly the friction you're hitting. |
| **(ii) Code-first registry + JSON** *(Approach A — recommended)* | One `config/participant_fields.php` entry per field, surfaced everywhere by a thin `App\Fields\ParticipantFields` accessor; values live in a single `details` JSON column. Adding a field = 1 config edit, **no migration**. | **Use** when *developers* own field changes and you want one obvious edit point with version control + code review. **Why not:** still a (tiny) deploy; non-developers can't add fields. |
| **(iii) Runtime admin-managed custom fields + JSON** *(Approach B)* | A `custom_fields` definition table + a `CustomField` Filament resource lets a panel admin add/remove fields with **no deploy**; values live in a `custom_data` JSON column. | **Use** only if *non-developer admins* genuinely need to add fields themselves between deploys. **Why not:** you permanently own a definition table + cache layer for runtime power you may use twice a year. |
| **(iv) Full EAV / dedicated package** | Entity-Attribute-Value tables (one row per field-value), or a Filament custom-fields plugin pinned to the fast-moving 5.x line. | **Over-engineering for eSIJIL — don't.** EAV makes every read a join and every report a nightmare for a handful of optional fields; a package adds tables, conventions and upgrade coupling for what is ~3 small files by hand. Reassess only at multi-tenant / field-versioning / hundreds-of-fields scale. |

---

## 3. Recommendation

**Adopt Approach A (code-first registry + JSON `details` column), as a hybrid where `membership_status` stays a first-class column. Escalate to Approach B only if a real need for runtime, admin-driven field management appears.**

### Why A over B

- The literal ask is *"add/remove fields easily, without over-complicating."* Approach A collapses the 8-edit tax to **one config entry** — the smallest thing that solves the actual problem. Approach B solves a *different* problem (no-deploy, admin-driven management) nobody has asked for, at the cost of a permanent definition-table + cache layer.
- Approach A keeps field definitions in **version control and code review** — a typo in an `in:` rule or a removed field shows up in a diff. Approach B moves that into runtime DB rows an admin edits live, with no review trail.
- Both share the *same* downside (JSON isn't indexed/sortable/reportable) and the *same* escape hatch (promote a hot field to a generated column). Approach A simply pays less for the same JSON trade-off.

### Why `membership_status` stays a first-class column (the hybrid)

`membership_status` is not long-tail data — it's a **core reporting/filtering axis**:

- It has a typed enum cast `MembershipStatus::class`.
- It's a **badge column** and the admin table's main `SelectFilter` — both need a real, indexed, type-safe column to work natively.
- It has a legacy import mapping (`LegacyEsijilSeeder::mapMembershipStatus()`).

Moving it into JSON would forfeit the enum, the native filter, and fast querying for zero benefit. **Keep `membership_status` (and `membership_notes`) as real columns; route only *new, optional, long-tail* fields through the registry + `details`.** This is also the least disruptive path — nothing existing changes.

### Phasing

1. **Phase 1 (now):** Implement Approach A — add the `details` JSON column + `ParticipantFields` accessor. Leave `membership_status`/`membership_notes` exactly as they are. New fields (jawatan, saiz baju, dietary notes, …) become one-line config entries.
2. **Phase 2 (only if needed):** If a hot field needs fast filter/sort/report, promote *that one path* to a MySQL stored generated column + index (one migration; the UI keeps reading from the registry). See §5.
3. **Phase 3 (only if a real requirement emerges):** If non-developer admins must add fields with no deploy, graduate to Approach B — the storage pattern (`json()` + `'array'` cast) and the "iterate-the-registry" UI code are identical, so A→B is incremental, not a rewrite.

---

## 4. Implementation sketch (Approach A, hybrid)

### Storage — one JSON column

```php
// new migration, run once
Schema::table('participants', function (Blueprint $table) {
    $table->json('details')->nullable()->after('membership_notes');
});
```

`Participant.php` — add `details` to fillable and cast it; **keep `membership_status` as-is**:

```php
#[Fillable([
    'full_name', 'email', 'nokp', 'phone', 'branch_id',
    'membership_status', 'membership_notes',   // unchanged — stays a real column
    'details',                                  // <-- flexible bag
])]

protected function casts(): array
{
    return [
        'membership_status' => MembershipStatus::class,  // unchanged
        'details' => 'array',
    ];
}
```

> The registration controller already does `$participant->fill($request->participantData())`, assigning the whole `details` array at once, so the plain `'array'` cast is sufficient (no `AsArrayObject` needed for eSIJIL's write pattern).

### Field-definition shape — one config file

```php
// config/participant_fields.php
return [
    'jawatan' => [
        'label'    => 'Jawatan',
        'type'     => 'text',                  // text | textarea | select
        'required' => false,
        'rules'    => ['nullable', 'string', 'max:255'],
        'sort'     => 20,
        'active'   => true,
        'scope'    => 'public',                // 'public' = on registration form; 'admin' = panel only
        'cert_var' => 'participant_jawatan',   // optional: exposes {{participant_jawatan}} in templates
    ],
    'shirt_size' => [
        'label'   => 'Saiz Baju',
        'type'    => 'select',
        'options' => ['S' => 'S', 'M' => 'M', 'L' => 'L', 'XL' => 'XL'],
        'rules'   => ['nullable'],
        'sort'    => 30,
        'active'  => true,
        'scope'   => 'admin',
    ],
];
```

### The single place that drives everything — `App\Fields\ParticipantFields`

```php
namespace App\Fields;

class ParticipantFields
{
    /** active + sorted */
    public static function all(): array
    {
        $fields = array_filter(config('participant_fields', []),
            fn (array $f) => ($f['active'] ?? true) === true);
        uasort($fields, fn ($a, $b) => ($a['sort'] ?? 999) <=> ($b['sort'] ?? 999));

        return $fields;
    }

    /** only fields collected on the public registration form */
    public static function public(): array
    {
        return array_filter(self::all(), fn ($f) => ($f['scope'] ?? 'public') === 'public');
    }

    /** validation rules keyed by "details.<key>" */
    public static function rules(string $scope = 'public'): array
    {
        $set = $scope === 'public' ? self::public() : self::all();
        $rules = [];

        foreach ($set as $key => $f) {
            $r = $f['rules'] ?? ['nullable'];
            if (($f['type'] ?? null) === 'select' && ! empty($f['options'])) {
                $r[] = 'in:' . implode(',', array_keys($f['options']));
            }
            $rules["details.$key"] = $r;
        }

        return $rules;
    }
}
```

This one accessor feeds every surface. Because every flex field lives under `details`, Filament dot-notation (`details.jawatan`) binds straight into the `'array'` cast:

- **Admin form** (`ParticipantForm.php`): spread generated `TextInput/Textarea/Select::make("details.$key")` components into the existing `Section`, beside the untouched `membership_status` block.
- **Table** (`ParticipantsTable.php`): map `TextColumn::make("details.$key")` (toggleable by default); add a per-select-field `SelectFilter` with a `->query(fn ($q, $data) => $q->where("details->$key", $data['value']))` closure.
- **Infolist** (`ParticipantInfolist.php`): map `TextEntry::make("details.$key")` with `formatStateUsing` to resolve select labels.
- **Public Blade** (`event-registrations/show.blade.php`): `@foreach (\App\Fields\ParticipantFields::public() as $key => $f)` rendering `name="details[{{ $key }}]"`. Replaces only the long-tail; the `membership_status` select stays.
- **Validation** (`StoreEventRegistrationRequest.php`): `array_merge([...fixed rules...], ParticipantFields::rules('public'))`; in `participantData()`, build `'details' => [...]` from the public registry.
- **Certificates** (`PdfmeCertificateRenderer::buildVariables()`): before the `return`, loop the registry and `array_merge` each field that has a `cert_var` into the variable map (with the core keys listed **last**, so a flex field can never override `participant_name`). Template authors then use `{{participant_jawatan}}`.

### How to ADD a field

1. Append one entry to `config/participant_fields.php` (key, label, type, rules, scope, optional `cert_var`).
2. `php artisan config:clear` and deploy.

It now appears in the admin form, infolist, table column, validation, the public form (if `scope: public`), and certificate variables (if `cert_var` set). **No migration. One file.**

### How to REMOVE a field

- **Soft (recommended):** set `'active' => false`. It vanishes from every surface and from validation; existing data stays harmlessly in `details`. Reversible, non-destructive.
- **Hard:** delete the config entry. Same UI effect; stale keys remain in `details` JSON until an optional one-off cleanup job strips them (`JSON_REMOVE`).

Either way there is **no destructive schema migration and no downtime** — the opposite of dropping a real column.

---

## 5. Honest trade-offs and mitigations

| Concern | Reality on JSON `details.*` | Mitigation |
|---------|-----------------------------|-----------|
| **Indexing / fast filter** | `where('details->jawatan', …)` works but is an **unindexed full scan**. Fine for thousands of participants; not for hot, high-volume filters. | Promote the one hot field to a **MySQL stored generated column + index** (`->storedAs("json_unquote(json_extract(details,'$.jawatan'))")`). Then `where`, `orderBy`, Filament `->searchable()`/`->sortable()` work natively while writes still go through `details`. One migration, no UI change. |
| **Sort / search in Filament** | `->sortable()` / `->searchable()` don't work natively on a `details.*` column. | Keep flex columns `toggleable(isToggledHiddenByDefault: true)`; add custom `->searchable(query: …)` / filter `->query()` closures, or promote to a generated column. |
| **Type safety / enums** | JSON values are loose strings; numbers sort lexicographically ("10" < "9"); no enum casting. | Keep anything needing an enum/type (like `membership_status`) as a **real column** (the hybrid). Coerce types in one place (the component `match` + the validation `rules()`). |
| **Validation** | `in:` rules are *stringly* built from `options` — a config typo silently changes accepted values; no DB constraint backs it. | Rules are centralized in `ParticipantFields::rules()` and live in version control / code review, so typos surface in diffs and tests. |
| **Reporting / BI / CSV** | Analysts can't comfortably `GROUP BY details->jawatan`; exports treat the column as opaque JSON. | This is the real ceiling. The moment a field becomes report-critical, **graduate it to a real column** (migration + backfill from `details` + point the registry at the real column). Anything report-critical should never live only in JSON. |
| **Uniqueness** | No clean `unique` constraint on a JSON path. | Keep unique fields (`nokp`, `email`) as real columns — they already are. |

**Bottom line:** JSON `details` is the right home for *optional, long-tail* fields (jawatan, saiz baju, dietary notes) that change more often than the schema should. The stable, queried, reported core — `full_name`, `email`, `nokp`, `phone`, `branch_id`, `membership_status` — stays in real columns. Promote a flex field to a real/generated column the instant it needs fast filtering or reporting. That keeps the simple things simple and confines the JSON cost to exactly the fields that benefit from it.

---

## 6. Same pattern for registrations (note)

If you later need flexible **per-registration** fields (event-specific questions like "Sesi pilihan" or "Keperluan pemakanan"), apply the identical pattern to `registrations` (a `details` JSON column + a `config/registration_fields.php` + a `RegistrationFields` accessor). The public form already writes registration data in the same controller flow, so the wiring mirrors §4 exactly.

---

*Recommended next step: implement Phase 1 (the `details` column + `ParticipantFields` accessor + wiring the existing Participant surfaces to iterate it, leaving `membership_status` untouched), with a test that adds a throwaway field via config and asserts it appears in the form/validation/details. Ask and I'll build it.*
