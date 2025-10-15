# Advanced-Reports: System Query Reports


## Specifying Fields

When specifying a field or using a field as part of calculation logic in an System Query, the
fields must be specified using the table/instrument alias and field name. For example:
`[instrument_1][record_id]`

When using fields in filter conditions and sorting, the raw field value will be used by default.
When displaying the field, the field value's label will be used by default. This can be overridden
using `:value` and `:label`, for example: `[instrument_1][choice_field]:value`

### Virtual Instrument Fields

There are several virtual fields for each instrument which can be used in an System Query:

* `[redcap_event_name]`
  * Event label (as field label), or unique event name (as field value)
* `[redcap_repeat_instance]`
  * Instance number
* `[redcap_data_access_group]`
  * Group label (as field label), or unique group name (as field value)

## Using Calculation Logic

In the options which support calculation logic, you can use any REDCap calculation logic/functions.
Smart variables which do not require a record context are also supported. Smart variables which
require a record context cannot be supported, for this functionality please refer to the virtual
instrument fields.

In addition to the documented REDCap functions, you can use `calcdate` in the calculation logic.
This is used in the same way as in the action tag (but without the `@` sign), and will return a date
or datetime in Y-M-D format. See the *date display format* option if you want to change the date
format of calculations.

### Report Specific Smart Variables

Instrument Query reports support the following smart variables: `[is-download]` (which has a value
of `1` if the report is being downloaded as a CSV file, and `0` otherwise), and `[is-api]` (which
has a value of `1` if the report is being accessed using the API, and `0` otherwise).

Query string smart variables are also supported.

#### Query String Smart Variables

&#9888;&#65039; **Important security note:**
Query string smart variables allow the user to supply *any value* via the URL. The module will
handle validating and escaping the values so they are safe to use in calculation logic, but care
must be taken to ensure that the user is not able to access data they shouldn't have access to via
their choice of parameter value.

Query string smart variables can be used on Instrument Query reports like any other REDCap smart
variable. They take a value supplied in the URL of the report to use in the query. The query string
smart variables are of the following formats (where *name* is the query string parameter):

* `[qint:name]` &ndash; the named query string value, as an integer
  * if the value is missing or is not an integer, the empty string is used
* `[qstr:name]` &ndash; the named query string value, as a string
  * if the value is missing, the empty string is used

To use query string smart variables, simply append the query string parameters to the end of the URL
of the report, for example:

<tt style="white-space:nowrap">https://your-redcap-instance/ExternalModules/?prefix=advanced_reports&pid=1&page=instrument_view&report_id=my_report<b>&my_parameter=AnExampleParameterValue</b></tt>

Parameter names can consist of lowercase letters, numbers, and underscores.

Bear in mind that some query string parameters are used by REDCap and by this module. You will need
to pick parameter names different from the following (unless you want to use those values):
* as_image
* download
* page
* pid
* prefix
* report_id
* report_state

## System Query Options

### Projects

Select the projects that you want to use in the System Query. Each project must be given an alias
which will be used in the next step when specifying which tables or instruments to query. There
are 6 ways to lookup a project:

* **This project**: uses the current project under which the report is saved
* **Title (append to this)**: selects the project where the project title is equal to the current
  project title with the specified value appended
* **Title (exact match)**: selects the project where the project title is equal to the specified
  value
* **Title (partial match)**: selects the project where the project title contains the specified
  value
* **Notes (exact match)**: selects the project where the project notes are equal to the specified
  value
* **Notes (partial match)**: selects the project where the project notes contain the specified
  value

The project lookup should be defined such that it can only match one project. If multiple projects
are matched, the lookup will fail.

### Tables / Instruments

Select the tables or instruments that you want to use in the System Query. Each table is
automatically filtered by the selected project. If you select the `redcap_data` table, you will be
prompted for the instrument name and it will be treated like a separate database table in the same
manner as in an Instrument Query.

When choosing `redcap_data` or `redcap_log_event`, you do not need to select the appropriate
numbered table for the project, this is determined automatically.

An alias must be entered for each table or instrument. This is then used to reference it in
subsequent logic.

### Join / Condition / Sorting / Fields to display

The options for join, condition, sorting, fields to display, and grouping are identical to those on
Instrument Queries.
