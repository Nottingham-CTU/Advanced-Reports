# Advanced-Reports: SQL Reports

## SQL Query

The SQL query **must** be a **SELECT** query. For safety, queries that do not start with SELECT will
not be accepted. This is to ensure that queries which alter the data or the database structure are
not inadvertantly run.

If your query starts with SELECT but is not accepted, make sure that there is no whitespace or
comments before the start of the query.

Queries will be tested when submitted. If the query does not start with SELECT or is invalid, it
will not be saved. Most browsers should show the error message as a tooltip when you place your
mouse cursor over the SQL query text box.

### Common Table Expressions

Due to the SELECT query limitation, it is not possible to construct a query starting with **WITH**,
as used in common table expressions. To get around this limitation, you can write your CTE query as
normal, then place it within a subquery.

For example
```sql
SELECT * FROM (
  -- CTE query goes here
) AS `out`
```

### Placeholders

The SQL query will accept several placeholders which will be dynamically replaced with the
appropriate values before the query is run.

#### Standard Placeholders

* `$$DAG$$` &ndash; the REDCap unique DAG ID of the user viewing the report
  * if the user is not in a DAG, *NULL* is used
* `$$DATATABLE$$` &ndash; the redcap_data table used by the current project
  * use `$$DATATABLE:pid$$`, where `pid` is a project ID, to get the redcap_data table used by
    that project
* `$$LOGTABLE$$` &ndash; the redcap_log_event table used by the current project
  * use `$$LOGTABLE:pid$$`, where `pid` is a project ID, to get the redcap_log_event table used by
    that project
* `$$PROJECT$$` &ndash; the current project ID
* `$$ROLE$$` &ndash; the REDCap unique role ID of the user viewing the report
  * if the user is not in a role, *NULL* is used
* `$$USER$$` &ndash; the username of the user viewing the report
* `$$WEBROOT$$` &ndash; the REDCap version directory web path for use in URLs

Placeholders which return strings will return the string appropriately escaped and encased in
quotes, with the exception of the `$$DATATABLE$$` and `$$LOGTABLE$$` placeholders.

#### Query String Placeholders

&#9888;&#65039; **Important security note:**
Query string placeholders allow the user to supply *any value* via the URL. The module will handle
validating and escaping the values so they are safe to use in a query, but care must be taken to
ensure that the user is not able to access data they shouldn't have access to via their choice of
parameter value.

Query string placeholders take a value supplied in the URL of the report to use in the query. The
query string placeholders are of the following formats (where *name* is the query string parameter):

* `$$QINT:name$$` &ndash; the named query string value, as an integer
  * if the value is missing or is not an integer, *NULL* is used
* `$$QSTR:name$$` &ndash; the named query string value, as a string
  * if the value is missing, *NULL* is used

To use query string parameters, simply append them to the end of the URL for the report, for
example:

<tt style="white-space:nowrap">https://your-redcap-instance/ExternalModules/?prefix=advanced_reports&pid=1&page=sql_view&report_id=my_report<b>&my_parameter=AnExampleParameterValue</b></tt>

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

## Result Type

The **result type** option determines how the SQL result will be interpreted by the report.

* **Normal dataset** means the report will render the data as retrieved from the database
* **EAV dataset** means the SQL result will be interpreted as entity-attribute-value data, and will
  be transformed by the report to resemble a normal dataset

### EAV Datasets

EAV datasets should contain exactly 3 columns:
* entity (row) ID
* attribute (column) name
* value

The row ID can be either omitted or included in the report. If it is included, it will be the first
column in the report and will be named according to the name of the first column in the SQL result.

Each entity-attribute pair (row ID and column name) should be unique in the dataset. If an
entity-attribute pair is repeated, the last value for the pair will be used.

Row IDs and column names will be placed in the report in the order they first appear in the dataset,
unless column names have been specified in the *result columns* option (see below).

If the dataset contains fewer or more than 3 columns, then the data will not be displayed. It is
impossible to render the report if fewer than 3 columns are used, and additional columns are
reserved for future use and explicitly not supported in order to avoid future incompatibility.

## Result Columns

The **result columns** option allows column names to be pre-specified for EAV datasets. This can be
useful for enforcing the presence and position of certain columns before the data is loaded. Columns
which are not specified here will be added to the output as they are encountered in the dataset.

Column names must be specified as a comma separated list. Names can be enclosed in quotes (") if
required.

