# Advanced-Reports: Instrument Query Reports


## Specifying Fields

When specifying a field or using a field as part of calculation logic in an Instrument Query, the
fields must be specified using the instrument name/alias and field name. For example:
`[instrument_1][record_id]`

When using fields in filter conditions and sorting, the raw field value will be used by default.
When displaying the field, the field value's label will be used by default. This can be overridden
using `:value` and `:label`, for example: `[instrument_1][choice_field]:value`

## Using Calculation Logic

In the options which support calculation logic, you can use any REDCap calculation logic/functions.
Some smart variables are also supported (those which do not require a record context).

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