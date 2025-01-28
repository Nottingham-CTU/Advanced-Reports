# Advanced-Reports

This REDCap module provides advanced reporting capability.



## Accessing Advanced Reports

The link to the advanced reports plugin pages will appear under the external modules heading once
the module has been enabled in a project. This will display the list of visible reports.
Administrators will also see a link to edit reports, as well as administrative options. Other users
granted report editing rights will also see the edit reports link.

To view a report, click on the link in the list of reports. Users with download rights will also get
a download link on the report page.



## Configuring Edit Rights

By default, only administrators can edit advanced reports. Edit rights can be granted to other users
by changing the system setting **Module configuration permissions in projects** to **Require
module-specific user privilege**. Edit rights can then be granted by assigning the module-specific
privilege to a user or role within the REDCap user rights page.

Note that some report types can only be edited by administrators regardless of these settings.



## Editing Advanced Reports

On the edit page, a new report can be added, or an existing report can be edited or deleted. There
is also a link here to view the report, which can be used even if the report is hidden from the main
report list.

The following report types are currently available:

* **Accumulation**
* **Gantt**
* **Instrument Query**
* **PDF**
* **Record Table**
* **SQL** (direct database query) \*

\* only administrators can edit

### Common report options

&#9888;&#65039; Unless otherwise specified, advanced reports do not apply a user's data viewing
rights or data export rights to the data returned and the user will be able to see all data returned
by the report. Keep this in mind when setting the user roles which can access each report.

* **Unique Report Name** is the identifier used by the system for your report
* **Report Label** is what the report is called in the list of reports
* **Report Category** *(optional)* is the heading under which the report appears in the list
* **Report Annotation** *(optional)* is extra text which can be used to explain the report. It is
  not shown on the report or in the reports list but may be included in simplified overviews of the
  reports.
* **Report is visible** determines whether the report is shown in the list
* **Grant access to roles** apart from users with edit rights, only the users with roles listed here
  can see the report
* **Allow downloads** \* determines whether a download link is provided for this report
* **Grant downloads to roles** \* apart from users with edit rights, only the users with roles
  listed here will see the download link.
* **Report can be saved to a field** \* determines whether the report can be saved into a file field
  on a record using the `@ADVANCED-REPORT-SAVE` action tag. For more information about this, see the
  section *saving reports to records* below.
* **Allow retrieval as image** \* determines whether the report can be retrieved as an image file,
  rather than as a REDCap page, which is useful for embedding a report in a data collection
  instrument
  * Append *&as_image=1* to the report URL to retrieve as an image.
  * Access rights are still enforced, so reports may not be visible if embedded in surveys.
* **Allow API access** \* determines whether the report can be accessed using the API
  * Note that the Advanced Reports API is separate from the REDCap API and uses its own per-report
    API keys.
  * The API key has to be sent as the value of the `api_key` field in a HTTP POST request.
  * Administrators have the option (in the module system settings) to prohibit non-administrators
    from saving reports where API access is enabled.
* **Allow public access** \* determines whether the report can be accessed publicly (without a
  REDCap login)
  * If the report can be downloaded or retrieved as image, this can be done by all users who access
    the report at the public URL.
  * Administrators have the option (in the module system settings) to prohibit non-administrators
    from saving reports where public access is enabled.

\* only available on some report types

### Accumulation report options

Accumulation reports will loop through a range of *accumulation numbers* and calculate a sum for all
records for each accumulation number.

* **Description** brief descriptive text to appear above the report
  * HTML &lt;a&gt; &lt;b&gt; and &lt;i&gt; tags as well as the placeholders `$$PROJECT$$`
    (project ID) and `$$WEBROOT$$` (REDCap version directory web path) can be used in the
    description.
* **Accumulation range** specify the start, end and step for the accumulation numbers
* **Accumulation logic** logic which is run for each record to obtain a number which is added to the
  total for each accumulation number
* **Group by** specify the logic which determines the record's group
  * There will be a row for each group on the report, the group name is the result of this logic and
    will be displayed in the left hand column.
* **Display format** specify how to display the accumulated totals
* **Column label logic** specify how to label the columns
* **Display options** choose to add a 'Total' row or show columns in reverse order

Please refer to the [Accumulation report instructions](README-Accumulation.md) for more information.

### Gantt report options

* **Label Fields** are shown on the left of the chart and identify each row
  * **Label Name** is the heading for the label column
  * **Label Field** is the event/field which contains the label data
* **Chart Categories** are the bars on the Gantt chart
  * **Name** is the name of the category, shown within the bars and in the legend at the bottom of
    the chart
  * **Start Date Field** is the event/field which contains the date which determines where the bar
    starts on the chart
  * **End Date Field** is the event/field which contains the date which determines where the bar
    ends on the chart
* **Filter Logic** is the (optional) conditional logic on which the records will be filtered

Calculated fields can be used for the start and end dates, the Gantt report will interpret the value
as a Unix timestamp representing a UTC date/time.

### Instrument Query report options

Instrument Query reports treat each instrument in the project like a database table which can be
joined and filtered as required. Instrument Query reports support REDCap calculation logic and some
smart variables.

* **Description** brief descriptive text to appear above the report
  * HTML &lt;a&gt; &lt;b&gt; and &lt;i&gt; tags as well as the placeholders `$$PROJECT$$`
    (project ID) and `$$WEBROOT$$` (REDCap version directory web path) can be used in the
    description.
* **Instruments** choose the instruments/forms from which to retrieve data
  * Each instrument is treated like a separate database table and can be joined together on any
    custom logic condition.
* **Condition** filter the dataset after all the instruments have been retrieved and joined
* **Sorting** field or logic on which to sort data
  * Append ` DESC` to sort in descending order.
* **Fields to display** specify each field or calculation logic for display in the report, with
  optional alias for the field heading
* **Hide missing data codes** if selected will show blank values instead of missing data codes

Please refer to the [Instrument Query report instructions](README-InstrumentQ.md) for more
information.

### PDF report options

PDF reports can format other reports into a PDF file.

* **Source Report** the source report from which to fetch the data
* **Paper Size** the paper size and orientation used in the PDF file
* **HTML Source** the template/layout of the report in HTML format

Please refer to the [PDF report instructions](README-PDF.md) for more information.

### Record Table report options

Record table reports will display all the fields in the selected instruments and events, including
all repeating instances, in a single row for each record.

The data returned in a Record Table will be automatically filtered according to the user's DAG and
data export rights.

* **Description** brief descriptive text to appear above the report
  * HTML &lt;a&gt; &lt;b&gt; and &lt;i&gt; tags as well as the placeholders `$$PROJECT$$`
    (project ID) and `$$WEBROOT$$` (REDCap version directory web path) can be used in the
    description.
* **Instruments** choose the instruments/forms to include
* **Events** choose the events to include
* **Hide missing data codes** if selected will show blank values instead of missing data codes

### SQL report options

* **Description** brief descriptive text to appear above the report
  * HTML &lt;a&gt; &lt;b&gt; and &lt;i&gt; tags as well as the placeholders `$$PROJECT$$`
    (project ID) and `$$WEBROOT$$` (REDCap version directory web path) can be used in the
    description.
* **SQL Query** enter SELECT query here
* **Result Type** choose how the SQL result is to be interpreted (normal or EAV)
* **Result Columns** pre-specify column names for EAV datasets

Please refer to the [SQL report instructions](README-SQL.md) for more information.



## Saving reports to records

It is possible to save an advanced report to a record in the project by creating a file upload field
and using the `@ADVANCED-REPORT-SAVE` action tag. The chosen report will be saved into the field
upon form submission. Use the action tag as follows:

`@ADVANCED-REPORT-SAVE('report_name','params')`

Where `report_name` is the unique report name, and `params` are any additional URL query string
parameters you want to use. The `params` value is optional and can be omitted. If specified,
`params` should be in the format `param1=value1&param2=value2`.

* `@IF` action tags are supported, so reports can be saved conditionally by placing the
  `@ADVANCED-REPORT-SAVE` action tag within an `@IF` action tag.
* The report will be saved to the field on *every* form submission, replacing any previous file. If
  you want existing files to be retained, use an `@IF` action tag to check if the field is empty.
* Piping and smart variables are supported in the `params` value.
* The `report_name` must reference a report which exists, is of a type which can be saved to a
  field, and has the option for saving to fields enabled. If these conditions are not met, the
  action tag will have no effect.
* Report access controls are not applied when saving to file fields and once saved, the report will
  be accessible to any user with access to that record/field.



## Project-level configuration options

The following project-level configuration options are deprecated. They are only visible to
administrators and only if at least one of them has been set. To permit non-administrators to edit
reports, please use the REDCap module permission as described above.

* Allow users with *project design and setup* rights to edit reports
* Allow users with *add/edit/organize reports* rights to edit reports
