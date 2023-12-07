# Advanced-Reports: Instrument Query Reports


## Specifying Fields

When specifying a field or using a field as part of calculation logic in an Instrument Query, the
fields must be specified using the instrument name/alias and field name. For example:
`[instrument_1][record_id]`

When using fields in filter conditions and sorting, the raw field value will be used by default.
When displaying the field, the field value's label will be used by default. This can be overridden
using `:value` and `:label`, for example: `[instrument_1][choice_field]:value`

When specifying *fields to display*, `:edit` can be appended to the field name to allow a value to
be edited directly from the report. This works only where the field is specified in isolation, not
as part of calculation logic. New values will not be able to be saved if the record is locked.
Administrators have the option (in the module system settings) to prohibit non-administrators from
saving reports where editable fields have been used.

### Virtual Instrument Fields

There are several virtual fields for each instrument which can be used in an Instrument Query:

* `[redcap_event_name]`
  * Event label (as field label), or unique event name (as field value)
* `[redcap_repeat_instance]`
  * Instance number
* `[redcap_data_access_group]`
  * Group label (as field label), or unique group name (as field value)
* `[redcap_form_url]`
  * URL of the REDCap data entry form
* `[redcap_survey_url]`
  * URL of the survey

## Using Calculation Logic

In the options which support calculation logic, you can use any REDCap calculation logic/functions.
Smart variables which do not require a record context are also supported. Smart variables which
require a record context cannot be supported, for this functionality please refer to the virtual
instrument fields.

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

## Instrument Query Options

### Instruments

Select the instruments that you want to use in the Instrument Query. Each instrument will be treated
like a separate database table and every row from the first instrument will be joined to every
row in the second instrument.

You can limit which rows are included in the join using the condition logic. For example:
`[instrument_1][record_id] = [instrument_2][record_id]`. When you add new instruments, condition
logic will automatically be added so only rows with matching record IDs will be joined.

Two types of join are supported. **Inner join** will simply join every row in the new instrument
with every row in the preceding data (subject to condition logic). This means that if you are
joining two instruments, only those rows with linking fields that match in both instruments will be
returned. **Left join** is similar, but will always return a row from the left side of the join,
even if there are no matching rows on the right side of the join (in this case the fields from the
new instrument being joined will be blank).

You can optionally specify an alias for each instrument. This is useful if you want to select the
same instrument more than once.

### Condition

The condition option allows you to specify a filter condition which will be applied to the final
result after the instrument joins.

### Sorting

Enter a field identifier or calculation logic into the sorting option to set the default order of
the records when displayed. You can append ` DESC` to the end to sort in descending order.

### Fields to display

Here you can specify which fields should be displayed. If no fields are specified, all fields from
the selected instruments will be displayed. You can specify calculation logic instead of a field
name to perform that calculation as part of the report.

### Grouping

This allows you to group the data by one or more of the fields, and apply functions to the remaining
fields. This is useful for example if you want to group the data by DAG, you can group on the DAG
field and return averages, sums, percentages or min/max values of the other fields.

For the **mean** and **percentage** functions, fractional values in the form `num/count` can be
supplied, such that the `num` and `count` values from each row are summed together, and the result
is the sum of the `num` values divided by the sum of the `count` values. Where a single number is
used without a `count` value, this is equivalent to a `count` value of `1`. The **percentage**
function is equivalent to the **mean** function, but the result is multiplied by 100 and displayed
with a percent sign.

If grouping is selected, it must be selected for all fields.
