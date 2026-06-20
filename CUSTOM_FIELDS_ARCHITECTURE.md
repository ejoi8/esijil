# eSIJIL — Custom Fields Architecture v2: Generalization Proposal

*Laravel 13.6 · Filament 5.4 · Livewire 4 · MySQL 8.4 (Laragon). Verified against the codebase, 2026-06-18. **Status: ✅ IMPLEMENTED (2026-06-18) — all sections below shipped; 132 tests passing, Pint clean.***

> **What you asked for:** make the design flexible and consistent. (1) Events should carry custom fields — **both** global event attributes **and** per-event registration questions. (2) The hard-coded **Branches** resource should go away; a branch is just a selectable value, so it belongs in the custom-fields system as a **dropdown field**.

---

## 1. Where we are today

The custom-fields system already exists and works for **participant** and **registration**: a `custom_fields` table holds field definitions; values live in each model's `details` JSON column; `App\Fields\CustomFields` surfaces them across admin form/table/infolist, the public form, validation, and certificate variables; `CustomFieldResource` (Settings, admin-only) manages them.

This proposal **generalizes** that foundation in three moves: add **event** as a third entity, add an **event scope** for per-event registration questions, and **retire Branch** into a dropdown field.

---

## 2. The generalized data model

### 2.1 One table, three entities, an optional event scope

`custom_fields` gains **one nullable column**: `event_id`.

| `entity` | `event_id` | Meaning | Managed where |
|---|---|---|---|
| `participant` | always `null` | Global participant attributes (e.g. **branch**) | Custom Fields resource |
| `event` | always `null` | Global event attributes (e.g. "sponsor", "category") | Custom Fields resource |
| `registration` | `null` | Global registration fields — apply to **every** event's form | Custom Fields resource |
| `registration` | `= <event id>` | **Per-event** questions — apply only to that one event's form | **Event → "Registration Form Fields"** tab |

`CustomFieldEntity` enum gains `case Event = 'event'`. A new `events.details` JSON column stores event attribute values (participants/registrations already have `details`).

### 2.2 Resolution rules (in `App\Fields\CustomFields`)

The accessor methods gain an optional event scope:

```
definitions(entity, ?Event $event = null): Collection<CustomField>
publicDefinitions(entity, ?Event $event = null)
rules(entity, context, prefix, ?Event $event = null)
```

- **participant / event** → `where entity = … AND event_id IS NULL` (event arg ignored).
- **registration, no event** → global only (`event_id IS NULL`). Used by the standalone admin form before an event is picked.
- **registration, with event** → **global + that event's** fields (`event_id IS NULL OR event_id = E.id`), de-duplicated by `key` with the event-specific field winning. Used by the public form and the event's own surfaces.

### 2.3 Uniqueness

`key` must be unique **per (entity, event scope)** — so two different events may each have their own `track` question, but one event can't define `track` twice, and global `track` can't clash with the same registration's per-event `track`.

Because MySQL treats `NULL`s as distinct in a unique index (which would let duplicate *global* keys slip through), I'll enforce it the same way we already enforced the soft-delete-aware uniques: a **driver-branched expression unique index on `(entity, key, COALESCE(event_id, 0))`** (functional index on MySQL, expression index on SQLite). `event_id` stays a real nullable FK with `cascadeOnDelete`, so deleting an event automatically removes its per-event questions.

---

## 3. Management surfaces

**A. Custom Fields resource (Settings → Custom Fields)** — unchanged role, now with `Event` in the entity dropdown. Manages **global** fields only (`event_id` stays null here). Admin-only.

**B. Event → "Registration Form Fields" relation manager (new)** — a tab on the Event edit page listing/creating `CustomField` rows for `entity = registration, event_id = this event`. This is the natural, discoverable home for per-event questions ("for *this* workshop, also ask their dietary needs"). It reuses the same field editor (label, key, type, options, required, scope, sort, active, help text, cert var); `entity` is fixed to `registration` and `event_id` is auto-set by the relation. Backed by a new `Event::registrationFields()` HasMany.

This split matches the mental model: **cross-cutting fields** live in Settings; **"questions for this event"** live on the event.

---

## 4. Where fields render (wiring matrix)

| Surface | Fields shown | Notes |
|---|---|---|
| Participant form/table/infolist | participant (global) | already wired; branch becomes one of these |
| **Event form/table/infolist** | **event (global)** | new wiring via `CustomFields::*(Event)` |
| Registration **admin** form (standalone) | registration global **+ selected event's** | `event_id` select becomes `live()`; the custom-field section re-renders for the chosen event |
| Event → Registrations relation manager (add registration) | registration global **+ this event's** | event is known (owner record) |
| **Public** registration form | registration global **+ this event's**, scope public/both | `publicDefinitions(Registration, $event)`; request validates with the event scope via `$this->route('event')` |
| Certificate variables | participant + event + registration(this event) fields with a `cert_var` | adds an event loop + event-scoped registration loop in `PdfmeCertificateRenderer::buildVariables()` |

The only non-trivial bit is the **reactive admin registration form**: a `live()` event select driving a dynamic custom-field group. Standard Filament; low risk.

---

## 5. Retiring Branch → a "Branch" dropdown field

This is the largest and riskiest part, because branch today is **not** a simple dropdown.

### 5.1 What branch is today
- `branches` table: `name`, `code` (PKB, JHR, …), `is_active`, `legacy_id`, soft-deletes.
- `participants.branch_id` FK (`nullOnDelete`).
- A `BranchSeeder` of the **16 canonical** PUSPANITA branches.
- **Legacy import machinery**: `LegacyEsijilSeeder` (~385 lines) resolves free-text `cawangan` → `branch_id` via a normalize-and-cache routine; `NormalizeLegacyBranchesSeeder` collapses aliases/RESERVE into canonical branches; `RefreshLegacyEsijilSeeder` prunes stale branches.

### 5.2 Target
- A participant custom field: `entity=participant, key='branch', type=select, scope=admin`, whose `options` are seeded from the canonical branch list (`['Selangor' => 'Selangor', …]`). Value stored in `participant.details['branch']`.

### 5.3 Migration plan
1. **Add-and-backfill migration:** create the `branch` custom field (options from existing active branches); copy each `participant.branch_id`'s branch **name** into `participant.details['branch']` (merging existing details). No-op on a fresh/test DB.
2. **Drop migration:** drop `participants.branch_id` (FK + column), then drop the `branches` table.
3. **Delete:** `Branch` model, `app/Filament/Resources/Branches/*`, `BranchPolicy`, `BranchFactory`, `BranchSeeder`.
4. **Edit:** `Participant` (remove `branch_id` fillable + `branch()` relation); `Permissions` (remove `'branch'` from `RESOURCES` **and** `STAFF_RESOURCES`); `ParticipantForm`/`ParticipantsTable`/`ParticipantInfolist` (remove branch Select/column/entry — replaced automatically by the custom field); `ParticipantFactory` (drop `branch_id`, optionally set `details['branch']`).
5. **Tests:** `ParticipantResourceTest` (branch column assertion → `details.branch`); `RolePermissionRegistryTest` (drop the `branch.view` staff assertion).

### 5.4 What this costs (the honest trade-offs)
- **Lost:** the `code` field; FK integrity; efficient SQL filtering/sorting/grouping by branch; "rename a branch once, everywhere updates" (renaming a field option does **not** rewrite stored values); the canonical list becomes dropdown options you maintain by hand.
- **The legacy seeders are the real bill.** `LegacyEsijilSeeder` / `NormalizeLegacyBranchesSeeder` / `RefreshLegacyEsijilSeeder` exist to normalize messy branch strings against the canonical table. If you still run legacy imports, that logic must be rewritten to resolve to a canonical **option value** and write `details['branch']` instead of `branch_id` — and it loses the DB anchor that currently guarantees consistency. This is meaningful, bug-prone work. **Open decision #1 below.**
- **Mitigation I recommend:** keep branch usable in the participants table by giving select-type custom fields an optional **JSON-based `SelectFilter`** (options from the field definition, `where details->'$.branch' = …`). Restores filtering (unindexed, fine at this data scale) without a real column.

---

## 6. Test plan
- Event custom fields: global event attribute saves via EventResource form; appears in table/infolist.
- Per-event question: created via the Event relation manager; appears on **that** event's public + admin registration form and **not** another event's; value saved to `registration.details`; validation enforced; a per-event `cert_var` resolves.
- Uniqueness: same key allowed across two events; rejected twice within one event; global vs per-event clash handled.
- Branch: backfill migration copies `branch_id` → `details['branch']`; participant form shows the Branch dropdown; value persists; (optional) the JSON branch filter works.
- Regression: full suite stays green; Pint clean.

## 7. Rollout order
1. `event_id` on `custom_fields` (+ expression unique index) → `events.details` → enum `Event` case.
2. `CustomFields` event-scope methods → wire Event surfaces → reactive admin registration form → public form + request → certificate variables → Event relation manager.
3. Branch: backfill migration → drop migration → delete/edit files → seeders → tests.
4. Re-seed permissions, Pint, full suite.

---

## 8. Decisions (resolved 2026-06-18 — approved to build)

- **Legacy seeders:** *one-shot, no longer needed* → **delete** `LegacyEsijilSeeder`, `NormalizeLegacyBranchesSeeder`, `RefreshLegacyEsijilSeeder`, `BranchSeeder` (and any `DatabaseSeeder` references). No rework.
- **Branch options:** seed from the **16 canonical** PUSPANITA branches only.
- **Branch scope:** **public + admin** (`scope = both`) — registrants pick their branch on the public form; admins manage the field and values.
- **Branch filtering:** **add** the JSON-based `SelectFilter` for select-type custom fields.

### Original open questions (now answered above)

1. **Legacy branch seeders** — do you still rely on `LegacyEsijilSeeder` / the normalize/refresh seeders to import data? If **yes**, I rework them to write `details['branch']` (more effort/risk). If **no / one-shot already done**, I delete them and skip that work.
2. **Branch options source** — seed the dropdown from the **16 canonical** branches only, or from **all** branch rows currently in your DB (includes any legacy/alias ones)?
3. **Branch visibility** — keep branch **admin-only** (as today), or also expose it on the **public** registration form?
4. **Branch table filtering** — add the JSON-based `SelectFilter` mitigation (recommended), or drop branch filtering entirely?

Once you confirm these (and approve the overall shape), I'll implement in the rollout order above, committing in logical chunks.
