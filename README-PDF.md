# Advanced-Reports: PDF Reports

PDF reports generate a PDF file from a *Source Report* (which provides the data to include in the
report) and the *HTML Source* (which provides the document template/layout).

## Source Report

The source report for a PDF report can be any Instrument Query.

## Paper Size

Choose the paper size and orientation for your PDF file.

## HTML Source

Create your document template using HTML. The module will convert this to a PDF.

You can use *field variables* and *logic tags* to include the data from the source report into your
PDF report.

**Field Variables** consist of the name of the field or column as output by the source report (this
will be the alias name, if one is specified), enclosed in square brackets, for example
`[record_id]`. Note that if the source report has square brackets in the column name, these will
also have to be included, for example: `[[baseline][record_id]]`.

**Logic Tags** control how the report is displayed:
* `<?loop>` will repeat the following section, once for each row in the source report
* `<?if(logic)>` where `logic` is REDCap calculation logic, will only show the following section if
  the logic evaluates to true
  * The logic can use any smart variable which does not require a record context, and query string
    smart variables are also supported - for more information about query string smart varibles
    please refer to the  [Instrument Query report instructions](README-InstrumentQ.md).
* `<?end>` ends the previous *loop* or *if* section

Logic tags can be nested, for example an *if* section can be placed within a *loop* section. A
*loop* section can be nested within another loop section, in which case a new loop is started (from
the first row of the source report) and after it is complete, the logic will return to the previous
loop where it left off.

If field variables are used outside of a *loop* section, the data from the first row will be used.

**Logic tag example:**
(where the source report outputs data for one record, identified by field `record_id`, with multiple
rows, one for each instance of a field `result`)
```
<p>Data for [record_id]:</p>
<ul>
<?loop>
<li>[result]<?if([result]<5)> -- Warning: low value<?end><?if([result]>95)> -- Warning: high value<?end></li>
<?end>
</ul>
```
