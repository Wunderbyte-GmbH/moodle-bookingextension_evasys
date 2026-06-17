# bookingextension_evasys – Developer Documentation

## Overview

`bookingextension_evasys` is a Moodle booking subplugin that integrates the
[EvaSys](https://www.evasys.de/) course evaluation platform with `mod_booking`.
When a teacher configures an evaluation questionnaire on a booking option, the
plugin automatically:

1. Registers the course and instructor in EvaSys via SOAP.
2. Creates a survey linked to that course.
3. Schedules it to open and close at the configured times.
4. Delivers evaluation reports to instructors and additional recipients after the
   survey closes.
5. Exposes the survey link and a QR code in the booking option view and through
   message placeholders.

### High-level flow

```
Booking option saved
        │
        ▼
evasys_send_to_api (adhoc task)
        │
        ├─ no prior EvaSys course → create_course → create_survey
        │                                               │
        │                              schedule evasys_open_survey (adhoc, at starttime)
        │                              schedule evasys_close_survey (adhoc, at endtime)
        │
        ├─ survey/form/teacher changed → update_survey (delete + recreate)
        │
        ├─ recipients changed only → update_course
        │
        └─ confirmdelete=1 → delete_survey + delete_course
```

---

## Directory structure

```
evasys/
├── lib.php                          Constants (field ID, header ID)
├── send_reports.php                 Admin page: manually resend reports for past surveys
├── classes/
│   ├── evasys.php                   Plugin entry-point (settings, option view, actions column)
│   ├── observer.php                 Event observer stub
│   ├── event/
│   │   └── evasys_surveycreated.php Fired after a survey is created or recreated
│   ├── external/
│   │   ├── get_evasysforms.php      AJAX endpoint: autocomplete for questionnaire forms
│   │   └── get_evasysperiods.php    AJAX endpoint: autocomplete for evaluation periods
│   ├── local/
│   │   ├── evasys_handler.php       Orchestration layer (course/survey lifecycle)
│   │   ├── evasys_helper_service.php Argument builders and data mappers
│   │   └── evasys_soap_service.php  Thin SOAP wrapper (one method per EvaSys SOAP call)
│   ├── option/fields/
│   │   └── evasys.php               Booking option form field (definition, save, load)
│   ├── placeholders/
│   │   ├── evasyssurveylink.php     Placeholder → clickable survey link
│   │   ├── evasyslinkforqr.php      Placeholder → clickable QR image link
│   │   ├── evasysqrcode.php         Placeholder → inline QR code image
│   │   ├── evasysevaluationstarttime.php  Placeholder → evaluation start date/time string
│   │   └── evasysevaluationendtime.php    Placeholder → evaluation end date/time string
│   ├── rules/
│   │   ├── rules/
│   │   │   └── rule_evasysevaluationtime.php  Booking rule: trigger at start/end/courseend
│   │   └── conditions/
│   │       └── select_organizers_in_bo.php    Rule condition: target organizers of option
│   ├── services/
│   │   └── evasysuser_profile_field_initializer.php  Creates the evasysid profile field on install
│   └── task/
│       ├── evasys_send_to_api.php   Adhoc: create/update/delete course & survey
│       ├── evasys_open_survey.php   Adhoc: open survey at start time
│       └── evasys_close_survey.php  Adhoc: close survey + send report at end time
├── amd/src/
│   ├── form_evasysforms_selector.js  AMD autocomplete datasource for forms
│   └── form_evasysperiods_selector.js AMD autocomplete datasource for periods
└── db/
    ├── install.xml    DB schema: bookingextension_evasys table
    ├── events.php     Event → observer bindings
    ├── services.php   External function declarations
    └── tasks.php      Adhoc task registration
```

---

## lib.php

### `MOD_BOOKING_OPTION_FIELD_EVASYS` (515)

Integer constant used as the execution-order ID for the EvaSys option field.
Booking option fields are executed in ascending ID order; 515 places EvaSys
after all core booking fields.

### `MOD_BOOKING_HEADER_EVASYS` (`'evasysheader'`)

String constant that identifies the collapsible section header in the booking
option form under which all EvaSys fields are grouped.

---

## classes/evasys.php — `class evasys`

Entry-point class for the subplugin. Implements `bookingextension_interface`
so the booking module can discover and call it generically.

### `get_plugin_name(): string`

Returns the localised plugin name from the language file.

### `contains_option_fields(): bool`

Returns `true`, signalling to the booking module that this plugin adds fields to
the booking option form.

### `get_option_fields_info_array(): array`

Returns a descriptor array for the `evasys` option field class. The booking
module reads this to instantiate the correct `field_base` subclass.

### `get_allowedruleeventkeys(): array`

Returns `['evasys_surveycreated']` — the list of event keys that booking rules
may react to from this plugin.

### `set_template_data_for_optionview(object $settings): array`

Builds the QR code data block shown in the booking option detail view.
Returns an empty array (nothing shown) or a one-element array with the QR image.

**Visibility rules:**
- Managers (`mod/booking:updatebooking`) see the QR as long as the survey hasn't ended.
- Enrolled participants and teachers see it only during the active window (`starttime`–`endtime`).
- Always hidden if `includeqrinoptionview` admin setting is off, or if no QR URL is stored yet.

### `load_data_for_settings_singleton(int $optionid): object`

Loads the EvaSys DB row for the given option ID into the booking settings
singleton. Returns an empty object when no row exists yet.

### `add_options_to_col_actions(object $settings, mixed $context): string`

Returns HTML for up to two dropdown items appended to the booking option actions
column (visible to managers and option editors only, while the survey is active):
1. A link to the QR code image (opens in a new tab).
2. A link to the survey itself (opens in a new tab).

### `load_settings(\part_of_admin_tree $adminroot, ...): void`

Registers the EvaSys admin settings page under `modbookingfolder`.
Settings: WSDL (hardcoded, see TODO), base URL, username, password, user profile
field mapping, report recipient role, QR-in-option-view toggle, two booking option
custom field mappings, default period, default sub-unit, and a manual
"resend reports" action button.

---

## classes/local/evasys_handler.php — `class evasys_handler`

Orchestration layer. All public methods combine SOAP calls and DB writes.
The SOAP client is created via `create_soap_client()` so that PHPUnit tests
receive a mock instead.

### `create_soap_client()`

Returns `evasys_soap_service` in production, or `evasys_soap_service_mock`
during PHPUnit runs (injected via `PHPUNIT_TEST` constant).

### `save_form(object &$formdata, object &$option): void`

Persists the EvaSys section of the booking option form to `bookingextension_evasys`.

- If `$formdata->evasys_booking_id` is empty: check DB for an existing row first
  (to avoid duplicates on concurrent saves), then INSERT. Writes the new row ID
  back into `$formdata->evasys_booking_id` so post-save steps can reference it.
- If `$formdata->evasys_booking_id` is set: UPDATE the existing row.

### `load_form(object &$data, object $settings): void`

Inverse of `save_form`. Reads the stored EvaSys record from `$settings->subpluginssettings['evasys']`
and maps it onto `$data` for form pre-filling. Does nothing if no record exists yet.

### `get_periods_for_settings(): array`

Fetches all EvaSys evaluation periods and formats them for `admin_setting_configselect`.
Keys are `"{numericId}-{base64(label)}"` — this composite format lets other code decode
the display label from the stored config value without a SOAP call.
Periods are returned in reverse order (most recent first).

### `get_periods_for_query(string $query): array`

Searches periods by name for the autocomplete AJAX endpoint. Returns
`['list' => [...], 'warnings' => '']`. If more than 100 match, returns an
empty list with a warning so the user refines the search.
Each list item's `id` is `"{numericId}-{base64(name)}"`.

### `get_forms_for_query(string $query): array`

Same shape as `get_periods_for_query` but reads from the local Moodle cache
(see `cached_forms()`) instead of calling SOAP on every keystroke.
An empty `$query` returns all forms.

### `get_allforms(): array`

Fetches all questionnaire forms for the configured sub-unit via SOAP.
Returns `[formId => formName]`. The sub-unit ID is the numeric prefix of the
`evasyssubunits` config value (stored as `"{id}-{base64name}"`).

### `get_recipients(): array`

Queries Moodle for all users holding the role configured in `rolereportrecipients`.
Returns `[userId => "Firstname Lastname (ID: x) | email"]` for use as the
"additional report recipients" autocomplete options on the booking option form.

### `get_subunits(): array`

Fetches EvaSys sub-units (Fachbereiche / departments) and formats them like
`get_periods_for_settings()` — composite `"{id}-{base64name}"` keys.

### `save_user(object $user): void`

Creates a user in EvaSys via `InsertUser` and writes the returned IDs back to the
user's Moodle custom profile field (configured via `evasyscategoryfielduser`).
The field stores `"externalId,internalId"` where:
- `externalId` = `"evasys_{moodleUserId}"`
- `internalId` = the numeric ID assigned by EvaSys (`m_nId`)

The `internalId` is the last element and is used in subsequent course inserts
to identify the primary instructor.

### `save_survey(array $args, int $id): object|null`

Calls `InsertCentralSurvey` via SOAP and stores the returned `m_nSurveyId`
in the `bookingextension_evasys` row identified by `$id`.

### `delete_survey(int $surveyid): bool`

Calls `DeleteSurvey` via SOAP. Used before update_survey or when the user
removes the questionnaire from a booking option.

### `open_survey(int $surveyid): bool`

Calls `OpenSurvey` via SOAP, transitioning the survey to data-collection phase.
Called by `evasys_open_survey` adhoc task at the configured start time.

### `close_survey(int $surveyid): bool`

Calls `CloseSurvey` via SOAP. Called in two situations:
1. Immediately after creation (surveys are open by default; closed until scheduled to open).
2. By `evasys_close_survey` adhoc task at the configured end time.

### `update_survey(int $surveyid, object $data, object $option, int $moodlecourseid): object|null`

**Delete-and-recreate** pattern — EvaSys has no true "update survey" SOAP call.

Steps:
1. If evaluation has already started: close, send report, wait for delivery task to complete.
2. Delete old survey and parent course in EvaSys.
3. Create new course and survey with updated data.
4. Close new survey immediately (scheduled task will re-open it).
5. Fetch and store new survey URL and QR code.
6. Fire `evasys_surveycreated` event.

### `create_survey(object $courseresponse, object $data, object $option): object|null`

Creates a new survey for an already-created course. Immediately closes the new survey,
fetches its URL and QR code, and fires `evasys_surveycreated`.
`$courseresponse` is the return value from `create_course()` / `insert_course()`.

### `aggregate_data_for_course_save($data, $option, $moodlecourseid, $evasyscourseid = null): array`

Assembles the SOAP argument array for `InsertCourse` / `UpdateCourse`.
**Does not make any SOAP or DB calls itself**, but as a side-effect registers any
teacher or recipient that lacks an EvaSys profile by calling `save_user()`.

Teacher→instructor mapping: teachers are sorted alphabetically by last name; the
first becomes the primary instructor (`m_nUserId`); the rest become secondary instructors.

Custom field mapping to EvaSys course custom fields:
| Field | Content |
|-------|---------|
| 1     | Booking option custom field 1 (`evasyscustomfield1`) |
| 2     | Booking option custom field 2 (`evasyscustomfield2`) |
| 3     | Course start date (dd.mm.yyyy) |
| 4     | Same as Field 1 |
| 5     | Secondary teacher full names (when `evasyscustomfield5 = fullname`) |

### `create_course(object $data, object $option, $moodlecourseid): object|null`

Calls `InsertCourse` and stores the returned `m_nCourseId` (internal) and
`m_sExternalId` (`"urise_{optionid}"`) in the DB row.

### `delete_course(int $internalid, int $tableid): bool`

Calls `DeleteCourse` (by internal ID). On success, deletes the
`bookingextension_evasys` DB row. Note: deleting a course in EvaSys also
removes all attached surveys.

### `update_course(object $data, object $newoption, int $tableid, int $moodlecourseid): object|null`

Calls `UpdateCourse` without touching surveys. Used when only course metadata
changes (e.g. a different set of report recipients) that does not require a
full survey delete-and-recreate cycle.

### `get_qrcode(int $id, string $url): string`

Builds a QR code URL via the external `api.qrserver.com` service
(`https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=…`) and
stores it in the DB row. Returns the URL string.

### `get_surveyurl(int $id, int $surveyid): object|null`

Retrieves the survey's direct online link via `GetPswdsBySurvey` SOAP call
(the only way to obtain the URL from the EvaSys API — see SOAP service notes)
and stores it in the DB row.

### `cached_forms(): array`

Returns `[formId => formTitle]` from the Moodle application cache
(`bookingextension_evasys/evasysforms`). On a cache miss, fetches all forms via
`get_allforms()`, then retrieves each form's full title individually via
`get_form()`. The cached value is `[$formsArray, $timestamp]` (index 0 = data,
index 1 = creation time).

### `send_report(int $surveyid): object|null`

Schedules a `SendResultsToInstructors` task in EvaSys via `SaveTasks`.
The task is scheduled 30 seconds in the future. Returns the task list object
containing the `TaskId` needed by `execute_task()`.

### `execute_task(int $taskid): object|null`

Immediately triggers a previously scheduled EvaSys task via `ExecuteTask`.
Must be called after `send_report()` when immediate delivery is needed
(e.g. before deleting a survey in `update_survey()`).

---

## classes/local/evasys_helper_service.php — `class evasys_helper_service`

Pure helper: data mapping and SOAP argument construction. No SOAP calls, no DB writes.

### `set_secondaryinstructors_for_save(array $secondaryinstructors): array`

Converts an array of Moodle user objects into an array of EvaSys `UserData`
objects for the `m_aoSecondaryInstructors` field of a course insert/update call.
The EvaSys internal user ID is extracted from the user's profile field
(`"externalId,internalId"` — last element).

### `map_form_to_record(object $formdata, object $option): object`

Maps the EvaSys section of submitted form data to a `bookingextension_evasys`
DB record object ready for `insert_record` / `update_record`.

Survey window calculation:
- `starttime = courseendtime + durationbeforestart` (negative offset, e.g. −7200 = 2 h before end)
- `endtime   = courseendtime + durationafterend`   (positive offset, e.g. 86400 = 24 h after end)

### `map_record_to_form(object &$data, object $record): void`

Inverse of `map_form_to_record`. Maps a DB row back onto the form default values
object for pre-filling when an existing booking option is edited.
`organizers` (comma-separated string) is split back into an array.

### `transform_return_to_array(array $list, string $key, string $value): array`

Converts a list of SOAP response objects into `[$element->$key => $element->$value]`.
Used to normalise period and sub-unit SOAP responses into plain select-option arrays.

### `set_args_insert_course(...): array`

Builds the argument array for `InsertCourse` / `UpdateCourse`.
Pass `$courseid = null` for a new course; pass the existing ID for an update.
The external ID is always `"urise_{$optionid}"`.

### `set_args_insert_user(...): object`

Builds a `UserData` stdClass for `InsertUser`.
External ID = `"evasys_{$userid}"`. Internal ID (`m_nId`) is `null` so EvaSys assigns one.

### `set_args_insert_survey(int $userid, int $internalcourseid, int $formid, int $periodid): array`

Builds the argument array for `InsertCentralSurvey`.
`sSurveyType = "c"` = central survey (managed by sub-unit admin, not individual instructor).

### `set_args_delete_survey(int $surveyid): array`

Argument array for `DeleteSurvey`. `IgnoreTwoStepDelete = false` respects any
EvaSys two-step deletion safety check.

### `set_args_delete_course(int $internalcourseid): array`

Argument array for `DeleteCourse` using `IdType = 'INTERNAL'` (numeric EvaSys ID).

### `set_args_get_qrcode(int $surveyid): array`

Argument array for the `GetQRCode` SOAP call. Not currently used — the plugin
generates QR codes via api.qrserver.com instead.

### `set_args_get_form(int $internalid): array`

Argument array for `GetForm`. `IncludeOnlyQuestions = true` and
`SkipPoleLabelsInheritance = true` reduce the response size; only
`FormId` and `FormTitle` are needed.

### `set_args_fetch_forms(int $subunitid): array`

Argument array for `GetAllForms` filtered to the given sub-unit, so only forms
available to that department are returned.

### `set_args_open_survey(int $surveyid): array`

Argument array for `OpenSurvey`.

### `set_args_close_survey_final(int $surveyid): array`

Argument array for `CloseSurvey` with `bSendReportToInstructor = false`.
Intended as a permanent close; currently identical to `set_args_close_survey`.

### `set_args_close_survey(int $surveyid): array`

Argument array for `CloseSurvey` with `bSendReportToInstructor = false`.
Used both on initial creation (keep closed until scheduled open) and by the
`evasys_close_survey` adhoc task.

### `set_args_get_surveyurl(int $surveyid): array`

Argument array for `GetPswdsBySurvey` with `nPswdCount = 0` (no new codes generated).
Despite the "passwords" name, this is the only way in the EvaSys SOAP API to retrieve
the survey's direct online link (`m_sDirectOnlineLink`).

### `set_args_send_report(int $surveyid): array`

Argument array for `SaveTasks`. Schedules a `SendResultsToInstructorsTask`
30 seconds in the future. Recipient placeholders:
- `1` = primary instructor
- `3` = secondary instructors
(Resolved by EvaSys at task execution time, not concrete user IDs.)

### `set_args_task_list(int $surveyid): array`

Positional argument array for `ListTasks`, filtered to a specific survey and to
task type `7` (`SendResultsToInstructors`). Arguments are positional (not named):
SubunitIDList, FormIDList, SurveyIDList, UserIDList, TaskTypeIDList, PeriodIDList.

### `wait_for_task($soap, $helper, int $surveyid): bool`

Polls `ListTasks` up to 10 times (6 s apart, max 60 s) until the
`SendResultsToInstructors` task for the given survey reaches a terminal state.

Terminal status codes:
| Code | Meaning |
|------|---------|
| 4    | Completed successfully |
| 5    | Completed with error |
| 6    | Cancelled |
| 7    | Expired |

Returns `false` if the task is still running after all attempts.

---

## classes/local/evasys_soap_service.php — `class evasys_soap_service`

Thin wrapper around PHP `SoapClient`. One public method per EvaSys SOAP operation.
All methods catch `SoapFault`, log it via `trace_soap_fault()`, and return `null` / `false`.

### `__construct(?string $endpoint, ?string $username, ?string $password, ?string $wsdl)`

Reads connection parameters from Moodle config if not passed explicitly.
Calls `parent::__construct()` (SoapClient) then sets the authentication SOAP header.

### `trace_soap_fault(string $operation, SoapFault $exception): void`

Writes SOAP fault details to the Moodle task output via `mtrace()`.

### `set_soap_header(): void`

Creates a WS-Security-style SOAP header with `Login` / `Password` credentials
in the `soapserver-v101.wsdl` namespace and attaches it to every subsequent call.

### `fetch_subunits(): object|null`

Calls `GetSubunits`. Returns the full sub-unit tree.

### `fetch_periods(): object|null`

Calls `GetAllPeriods`. Returns all evaluation periods.

### `get_period(array $args): object|null`

Calls `GetPeriod` for a single period by ID.

### `fetch_forms(array $args): object|null`

Calls `GetAllForms` with sub-unit usage restrictions.

### `get_form(array $args): object|null`

Calls `GetForm` for a single form by internal ID.

### `insert_user(object $args): object|null`

Calls `InsertUser`. Returns the created user object including `m_sExternalId` and `m_nId`.

### `insert_course(array $args): object|null`

Calls `InsertCourse`. Returns the created course object including `m_nCourseId` and `m_sExternalId`.

### `update_course(array $args): object|null`

Calls `UpdateCourse`.

### `delete_course(array $args): bool`

Calls `DeleteCourse`. Returns `false` on SOAP fault.

### `insert_survey(array $args): object|null`

Calls `InsertCentralSurvey`. Returns the created survey object including `m_nSurveyId`.

### `delete_survey(array $args): bool`

Calls `DeleteSurvey`.

### `open_survey(array $args): bool`

Calls `OpenSurvey`. Transitions the survey to data-collection phase.

### `close_survey(array $args): bool`

Calls `CloseSurvey`. Ends data collection.

### `get_surveyurl(array $args): object|null`

Calls `GetPswdsBySurvey`. Despite the name this call returns the
`OnlineCodes` structure which contains `m_sDirectOnlineLink` — the public
survey URL. It is the only way to retrieve the direct URL via SOAP.

### `send_report(array $args): object|null`

Calls `SaveTasks` to schedule a `SendResultsToInstructors` task.

### `list_tasks(array $args): object|null`

Calls `ListTasks`. Used by `wait_for_task()` to poll task completion status.

### `execute_task(int $taskid): object|null`

Calls `ExecuteTask` to immediately trigger a previously saved task.

---

## classes/option/fields/evasys.php — `class evasys` (field)

Implements `field_base` — the booking option form field API from `mod_booking`.
Saved as a post-save field (`MOD_BOOKING_EXECUTION_POSTSAVE`) because it needs
the option ID before it can create SOAP records.

### Static properties of interest

| Property | Value | Meaning |
|----------|-------|---------|
| `$id` | `MOD_BOOKING_OPTION_FIELD_EVASYS` (515) | Execution order |
| `$save` | `MOD_BOOKING_EXECUTION_POSTSAVE` | Runs after the main option is saved |
| `$header` | `MOD_BOOKING_HEADER_EVASYS` | Form section header |
| `$evasyskeys` | see code | All form field names watched for changes |
| `$relevantkeyssurvey` | `['evasys_form', 'evasysperiods']` | Changes that require survey recreation |
| `$relevantkeyscourse` | `['evasys_other_report_recipients']` | Changes that require only a course update |

### `prepare_save_field(stdClass &$formdata, stdClass &$newoption, ...): array`

Compares each EvaSys form field against its previously stored value to detect what
changed. Returns a `['changes' => [...]]` array consumed by `changes_collected_action`.

### `validation(array $formdata, array $files, array &$errors): array`

Form validation: requires a confirmation checkbox when the questionnaire is removed
from an already-saved option. Blocks EvaSys if the option is a self-learning course.

### `instance_form_definition(MoodleQuickForm &$mform, ...): void`

Adds all EvaSys form elements to the booking option form:
- `evasys_form` — autocomplete (AJAX, form_evasysforms_selector)
- `evasys_confirmdelete` — checkbox, shown only when questionnaire is being removed
- `evasys_timemode` — hidden (always 0; duration mode only for now)
- `evasys_durationbeforestart` — select (hours before course end)
- `evasys_durationafterend` — select (hours after course end)
- `evasys_other_report_recipients` — multi-autocomplete
- `evasysperiods` — autocomplete (AJAX, form_evasysperiods_selector)
- `evasys_notifyparticipants` — checkbox
- Several hidden fields for IDs and read-only data

All fields except the form selector are hidden when no questionnaire is selected.

### `set_data(stdClass &$data, booking_option_settings $settings): void`

Populates form defaults from the DB via `evasys_handler::load_form()`.
Skipped when the form is loading a copy of another option (`oldcopyoptionid` set).

### `definition_after_data(MoodleQuickForm &$mform, $formdata): void`

Freezes all EvaSys fields once the evaluation start time has passed (the survey
is already running and must not be changed).
Also sets a hidden `evasys_delete` flag to `1` when the form is submitted with
the questionnaire field cleared — triggers deletion in `changes_collected_action`.

### `save_data(object &$formdata, object &$option): void`

Persists EvaSys form data via `evasys_handler::save_form()`. Returns early if
no questionnaire was selected or no teachers are assigned.

### `changes_collected_action(array $changes, object $data, object $newoption, object $originaloption): void`

The main dispatch point after a booking option save. Determines what changed
(teacher list, option name, EvaSys-specific fields) and queues the appropriate
`evasys_send_to_api` adhoc task with a detailed payload. Also reschedules the
open/close tasks if the evaluation window changed without a survey recreation.

---

## classes/task/evasys_send_to_api.php

Adhoc task. Queued by `evasys::changes_collected_action()` after every booking
option save that touches EvaSys data.

### `execute(): void`

Reads `$taskdata` (set via `set_custom_data`) and branches:

| Condition | Action |
|-----------|--------|
| No prior EvaSys course (`evasys_courseidexternal` empty) and a form is set | `create_course` → `create_survey` → schedule open/close tasks |
| `evasys_confirmdelete = 1` | `delete_survey` → `delete_course` |
| Teacher, name, or survey-relevant field changed | `update_survey` → reschedule open/close tasks |
| Only course-relevant field changed | `update_course` |
| Only evaluation window changed (no survey change) | reschedule open/close tasks |

Self-learning courses are skipped silently (EvaSys and self-learning are incompatible).

---

## classes/task/evasys_open_survey.php

Adhoc task. Scheduled by `evasys_send_to_api` to run at `evasys_starttime`.

### `execute(): void`

Before opening, validates that the DB row still has the same `starttime` as this
task's scheduled run time. This guards against stale tasks opening a superseded
survey when the booking option was edited after the task was queued.

---

## classes/task/evasys_close_survey.php

Adhoc task. Scheduled by `evasys_send_to_api` to run at `evasys_endtime`.

### `execute(): void`

Same stale-task guard as `evasys_open_survey` (checks `endtime`). After closing,
calls `send_report()` to deliver the evaluation results to instructors.
Also purges the booking option cache.

---

## classes/event/evasys_surveycreated.php

Moodle event fired after a survey is created or recreated. CRUD type `r` (read),
teaching level. Booking rules can react to this event via the `evasys_surveycreated`
event key.

### `init(): void`

Sets event metadata: CRUD=`r`, level=`LEVEL_TEACHING`, table=`bookingextension_evasys`.

### `get_name(): string`

Returns the localised event name.

### `get_description(): string`

Returns a localised description including the `objectid` (booking option ID).

---

## classes/external/ — External API

Both classes follow the Moodle external API pattern used by autocomplete AMD modules.

### `get_evasysforms` — `execute(string $query): array`

Called by `form_evasysforms_selector.js`. Delegates to
`evasys_handler::get_forms_for_query()`. Returns `['list', 'warnings']`.

### `get_evasysperiods` — `execute(string $query): array`

Called by `form_evasysperiods_selector.js`. Delegates to
`evasys_handler::get_periods_for_query()`. Returns `['list', 'warnings']`.

Both return list items with `id = "{numericId}-{base64(name)}"` so the AMD
`valuehtmlcallback` can decode the display name client-side.

---

## classes/placeholders/

All placeholder classes extend `placeholder_base` and implement `return_value()` and
`is_applicable()`. They read from the `bookingextension_evasys` table by `optionid`
and throw `moodle_exception('placeholdernotresolved')` if no record exists.

| Class | Placeholder tag | Returns |
|-------|----------------|---------|
| `evasyssurveylink` | `{evasyssurveylink}` | Clickable HTML link to the survey (http→https upgrade applied) |
| `evasyslinkforqr` | `{evasyslinkforqr}` | Clickable HTML link to the QR image |
| `evasysqrcode` | `{evasysqrcode}` | Inline `<img>` of the QR code (150×150) |
| `evasysevaluationstarttime` | `{evasysevaluationstarttime}` | Start date/time as `"dd.mm.YYYY HH:MM"` |
| `evasysevaluationendtime` | `{evasysevaluationendtime}` | End date/time as `"dd.mm.YYYY HH:MM"` |

---

## classes/rules/

### `rule_evasysevaluationtime`

A booking rule that triggers a message action at the EvaSys evaluation start time,
end time, or course end time. Stores the chosen date field in `rulejson->ruledata->datefield`.

#### `set_ruledata(stdClass $record): void` / `set_ruledata_from_json(string $json): void`

Loads rule configuration from a DB record or raw JSON string.

#### `add_rule_to_mform(MoodleQuickForm &$mform, ...): void`

Adds a dropdown to the rule form letting the admin choose which timestamp to trigger on:
evaluation start, evaluation end, or course end.

#### `get_name_of_rule(bool $localized = true): string`

Returns the localised or internal rule name.

#### `save_rule(stdClass &$data): int`

Saves/updates the `booking_rules` DB row and encodes the datefield choice into `rulejson`.

#### `set_defaults(stdClass &$data, stdClass $record): void`

Pre-fills the rule edit form.

#### `execute(int $optionid = 0, int $userid = 0): void`

Fetches matching booking options via `get_records_for_execution()` and calls the
configured action for each record.

#### `check_if_rule_still_applies(...): bool`

Called from adhoc task execution to verify the rule is still active and the
scheduled timestamp still matches. Returns `false` if the option was edited.

#### `get_records_for_execution(int $optionid, int $userid, bool $testmode, int $nextruntime): array`

Builds and executes the SQL that identifies which booking options and users this rule
applies to. In test mode the timestamp check is skipped.
An hour of tolerance (`−3600`) is added to the timestamp filter to account for cron delays.
Only options with `notifyparticipants = 1` are included.

### `select_organizers_in_bo`

A booking rule condition that targets users stored in the `organizers` column
of the `bookingextension_evasys` table.

#### `execute(stdClass &$sql, array &$params): void`

Appends a JOIN on `bookingextension_evasys` and a LIKE-based join on `{user}`
that simulates a `IN(...)` check against the comma-separated `organizers` string.
Also appends a unique key (`"{optionid}-{userid}"`) to prevent duplicate rows.

---

## classes/services/evasysuser_profile_field_initializer.php

### `ensure_evasyscustomfield_exists(): void`

Creates the `evasysid` custom user profile field in the `evasys` category if it does
not already exist. Called during plugin install (`db/install.php`).
The field is hidden (`visible = 0`), locked (`locked = 1`), and not required.
It stores the `"externalId,internalId"` string written by `save_user()`.

---

## send_reports.php

Admin-only page (requires `moodle/site:config`). Queries all `bookingextension_evasys`
rows where a survey has ended (`surveyid IS NOT NULL AND endtime < now`) and calls
`send_report()` for each. Redirects back to the settings page with a success or
warning notification showing how many reports were sent vs. failed.
Linked from the admin settings page as a manual "resend" button.

---

## Known issues / TODOs

These are unintuitive or potentially problematic code patterns that should be reviewed:

1. **Hardcoded WSDL** (`classes/evasys.php`, `load_settings`):
   The `evasyswsdl` config is force-set on every settings page load to the University
   of Vienna's EvaSys instance. Other institutions cannot configure a different WSDL
   without code changes.

2. **Misleading parameter name** (`evasys_handler.php`, `aggregate_data_for_course_save` and
   `create_course`, `update_course`, `update_survey`):
   The parameter `$moodlecourseid` is actually `$COURSE->category` — a Moodle **category**
   ID, not a course ID.

3. **HTML typos** (`classes/evasys.php`, `add_options_to_col_actions`):
   - `<i … aria-hidden="true""` — stray extra `"` on the `aria-hidden` attribute.
   - `target="_blank" "` — stray extra `"` on the second anchor tag.

4. **`$qrcode` unused** (`evasys_handler.php`, `create_survey` and `update_survey`):
   The return value of `get_qrcode()` is assigned to `$qrcode` but never used.

5. **`$execution` unused** (`evasys_handler.php`, `update_survey`):
   The return value of `execute_task()` is assigned to `$execution` but never checked.
   A failed task execution is silently ignored.

6. **Duplicate `evasys_timemode` assignment** (`evasys_helper_service.php`, `map_record_to_form`):
   `$data->evasys_timemode` is assigned twice from the same source (`$record->timemode`).

7. **Identical method pair** (`evasys_helper_service.php`):
   `set_args_close_survey_final()` and `set_args_close_survey()` have identical bodies.
   The "final" variant was likely intended to differ (e.g. `bSendReportToInstructor = true`).

8. **Undefined variables in catch blocks** (`evasys_soap_service.php`, `fetch_periods` and `get_form`):
   The debug event in the catch block references `$optionid` and `$USER`, which are not
   defined in those methods. If booking debug mode is on, this triggers a PHP notice.

9. **`$taskdata` variable reuse** (`evasys_send_to_api.php`, `execute`):
   The variable `$taskdata` is first the original task payload object, then overwritten
   with a plain array `['surveyid' => ..., 'optionid' => ...]`. The code avoids bugs
   because `$updatecourse` and `$updatesurvey` are mutually exclusive, but the pattern
   is confusing and fragile.

10. **QR code external dependency** (`evasys_handler.php`, `get_qrcode`):
    QR code images are served by `api.qrserver.com`. This creates an external network
    dependency and may not work in air-gapped deployments.

11. **Option field constant comment mismatch** (`lib.php`):
    The comment says "Currently supported range: 321-329" but the constant value is 515.
