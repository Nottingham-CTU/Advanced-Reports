# Advanced-Reports: Accumulation Reports

## Using Calculation Logic

In the options which support calculation logic, you can use any REDCap calculation logic/functions
and Smart Variables. Additionally the `[accumulation-number]` Smart Variable is supported in the
*accumulation logic*, *group by* and *custom label logic* options.

## Accumulation range

The accumulation range determines the first and last accumulation number, as well as the step which
increments or decrements the accumulation number by the specified amount. If the start number is
less than the end number, the accumulation number is incremented by the step amount each time. If
the start number is greater than the end number, the accumulation number is decremented by the step
amount each time.

The step amount is a number greater than 0. If not set it defaults to 1.

The start and end values accept REDCap calculation logic. This may be useful for generating a range
based on dates, e.g. using logic such as `year('today')`.

## Accumulation logic

For each record and accumulation number, the accumulation logic will be run to get a result number.
These results will be summed together to obtain the cumulative value which is used in the report.

## Group by

If logic is entered here, it will be run for each record to get a *group name* which is used to
group the records together. The cumulative values will be split by group in the report.

## Display format

This provides options for how to present the data on the report. This does not apply to the API,
which will always contain all values.

* **Cumulative only** shows only the cumulative values calculated.
* **Change only** shows only the difference between the cumulative value and the cumulative value in
  the previous column.
* **Change only (omit + sign for increase)** is the same as *change only* but positive changes are
  not prefixed with a plus sign.
* **Cumulative with change in parentheses** shows the cumulative value with the change in
  parentheses appended.
  
## Column label logic

If logic is entered here, the column labels will be transformed according to the logic. If this is
not used, then accumulation numbers will be used for the column labels.

## Display options

These are extra options which control how the report is displayed. These do not apply to the API.

* **Add a 'Total' row** adds an extra row at the bottom of the table summing the values for all
  groups.
* **Columns in reverse order** displays the columns right to left instead of left to right. This
  differs from swapping the start/end of the accumulation range, as it does not affect the
  calculation of difference values.