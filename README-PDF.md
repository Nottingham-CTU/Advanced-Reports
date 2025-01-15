# Advanced-Reports: PDF Reports

PDF reports generate a PDF file from a *Source Report* (which provides the data to include in the
report) and the *HTML Source* (which provides the document template/layout).

## Source Report

The source report for a PDF report can be any Instrument Query.

SQL reports can also be used as the source report if this feature has been enabled by an
administrator using the option in the module system settings.

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
  * Field variables and set variables (see *setvar* below) are supported in the logic.
  * The logic can use any smart variable which does not require a record context, and query string
    smart variables are also supported - for more information about query string smart varibles
    please refer to the  [Instrument Query report instructions](README-InstrumentQ.md).
* `<?end>` ends the previous *loop* or *if* section
* `<?setvar[variable](logic)>` where `variable` is the name used to refer to the new variable, and
  `logic` is the REDCap calculation logic which is evaluated to provide the value for the new
  variable
  * To use the variable after it has been set, refer to it in the same manner as for field variables
    i.e. `[variable]`
  * Set variables cannot have a name which contains square brackets.
  * If a set variable has the same name as a field variable, the set variable will take priority.

Logic tags can be nested, for example an *if* section can be placed within a *loop* section. A
*loop* section can be nested within another loop section, in which case a new loop is started (from
the first row of the source report) and after it is complete, the logic will return to the previous
loop where it left off.

If field variables are used outside of a *loop* section, the data from the first row will be used.

**Logic tag example:**<br>
(where the source report outputs data for all records, identified by field `record_id`, with
multiple rows per record, one for each instance of a field `result`)
```
<?setvar[section_record]('')>
<?loop>
<?if([section_record]<>[record_id])>
<?setvar[section_record]([record_id])>
<p>Data for [record_id]:</p>
<ul>
<?loop>
<?if([section_record]=[record_id])>
<li>[result]<?if([result]<5)> -- Warning: low value<?end><?if([result]>95)> -- Warning: high value<?end></li>
<?end>
<?end>
</ul>
<?end>
<?end>
```
