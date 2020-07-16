# Advanced-Reports

This REDCap module provides advanced reporting capability.



## Project-level configuration options

This module provides two project-level configuration options, which are only visible to
administrators.

* Allow users with *project design and setup* rights to edit reports
* Allow users with *add/edit/organize reports* rights to edit reports

The rights referred to in these options are REDCap's user rights. This can be used to provide edit
rights for advanced reports to users other than administrators. Note however that some report types
can only be edited by administrators regardless of these settings.

The configuration for the reports themselves are located on the edit pages within the module's
plugin pages.



## Accessing Advanced Reports

The link to the advanced reports plugin pages will appear under the external modules heading once
the module has been enabled in a project. This will display the list of visible reports.
Administrators will also see a link to edit reports, as well as administrative options. Other users
granted report editing rights will also see the edit reports link.

To view a report, click on the link in the list of reports. Users with download rights will also get
a download link on the report page.



## Editing Advanced Reports

On the edit page, a new report can be added, or an existing report can be edited or deleted. There
is also a link here to view the report, which can be used even if the report is hidden from the main
report list.

The following report types are currently available:

* **SQL** (direct database query) \*

\* only administrators can edit

### Common report options

* **Unique Report Name** is the identifier used by the system for your report
* **Report Label** is what the report is called in the list of reports
* **Report Category** *(optional)* is the heading under which the report appears in the list
* **Report is visible** determines whether the report is shown in the list
* **Grant access to roles** apart from users with edit rights, only the users with roles listed here
  can see the report
* **Allow downloads** determines whether a download link is provided for this report
* **Grant downloads to roles** apart from users with edit rights, only the users with roles listed
  here will see the download link.

### SQL report options

* **SQL Query** enter SELECT query here
