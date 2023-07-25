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

## Using Calculation Logic

In the options which support calculation logic, you can use any REDCap calculation logic/functions.
Some smart variables are also supported (those which do not require a record context).

### Report Specific Smart Variables

Instrument Query reports support the `[is-download]` smart variable, which has a value of `1` if the
report is being downloaded as a CSV file, and `0` otherwise.

Query string smart variables are also supported.

#### Query String Smart Variables

:warning: **Important security note:**
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
like a separate database table and every record from the first instrument will be joined to every
row in the second instrument. You can limit which rows are included in the join using the ON
condition logic. For example: `[instrument_1][record_id] = [instrument_2][record_id]`

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