<?php

/*
|--------------------------------------------------------------------------
| Flexible participant fields
|--------------------------------------------------------------------------
|
| Optional, long-tail participant attributes. Every field defined here is
| surfaced automatically by App\Fields\ParticipantFields across the admin
| form/table/infolist, the public registration form, validation, and (if a
| `cert_var` is set) certificate template variables — values are stored in the
| participants.details JSON column. Adding a field is ONE entry here; no
| migration. See FLEXIBLE_FIELDS.md.
|
| Keep stable / queried / reported attributes (e.g. membership_status) as real
| columns instead — JSON values are not indexed, sortable or easily reportable.
|
| Field shape (key => definition):
|   'jawatan' => [
|       'label'    => 'Jawatan',                 // display label (Malay)
|       'type'     => 'text',                    // text | textarea | select
|       'options'  => ['a' => 'A', 'b' => 'B'],  // required for type 'select'
|       'required' => false,
|       'rules'    => ['nullable', 'string', 'max:255'], // Laravel rules (in: appended for selects)
|       'sort'     => 20,                         // display order
|       'active'   => true,                       // false = hidden everywhere, data kept
|       'scope'    => 'public',                   // 'public' = on registration form; 'admin' = panel only
|       'cert_var' => 'participant_jawatan',      // optional: {{participant_jawatan}} in templates
|   ],
|
*/

return [
    //
];
