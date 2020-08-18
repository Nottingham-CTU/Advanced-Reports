<?php
/**
 *	SQL Reports edit page.
 */



// Check user can edit SQL reports and verify the report exists and is an SQL report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'sql' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'sql' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );



// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// Validate data
	if ( substr( strtolower( $_POST['sql_query'] ), 0, 7 ) != 'select ' )
	{
		exit;
	}

	// Save data
	foreach ( [ 'label', 'category', 'visible', 'download', 'roles_access', 'roles_download' ]
	          as $configSetting )
	{
		$configValue = $_POST["report_$configSetting"];
		if ( in_array( $configSetting, [ 'visible', 'download' ] ) )
		{
			$configValue = $configValue == 'Y' ? true : false;
		}
		elseif ( trim( $configValue ) === '' )
		{
			$configValue = null;
		}
		$module->setReportConfig( $reportID, $configSetting, $configValue );
	}
	$reportData = [ 'sql_query' => $_POST['sql_query'] ];
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit SQL Report: <?php echo "$reportID\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' )
?>" class="fas fa-arrow-circle-left fs11"> Back to edit reports</a>
</p>
<form method="post">
 <table class="mod-advrep-formtable">
  <tr><th colspan="2">Report Label and Category</th></tr>
  <tr>
   <td>Report Label</td>
   <td>
    <input type="text" name="report_label" required
           value="<?php echo htmlspecialchars( $reportConfig['label'] ); ?>">
   </td>
  </tr>
  <tr>
   <td>Report Category</td>
   <td>
    <input type="text" name="report_category"
           value="<?php echo htmlspecialchars( $reportConfig['category'] ); ?>">
   </td>
  </tr>
  <tr><th colspan="2">Access Permissions</th></tr>
  <tr>
   <td>Report is visible</td>
   <td>
    <label>
     <input type="radio" name="report_visible" value="Y" required<?php
echo $reportConfig['visible'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_visible" value="N" required<?php
echo $reportConfig['visible'] ? '' : ' checked'; ?>> No
    </label>
   </td>
  </tr>
  <tr>
   <td>Grant access to roles</td>
   <td>
    <textarea name="report_roles_access"><?php echo $reportConfig['roles_access']; ?></textarea>
    <br>
    <span style="font-size:90%">
     Enter each role name on a separate line.
     <br>
     If left blank, the report will be accessible to users with edit access.
     <br>
     Enter * to grant access to all users.
    </span>
   </td>
  </tr>
  <tr>
   <td>Allow downloads</td>
   <td>
    <label>
     <input type="radio" name="report_download" value="Y" required<?php
echo $reportConfig['download'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_download" value="N" required<?php
echo $reportConfig['download'] ? '' : ' checked'; ?>> No
    </label>
   </td>
  </tr>
  <tr>
   <td>Grant downloads to roles</td>
   <td>
    <textarea name="report_roles_download"><?php echo $reportConfig['roles_download']; ?></textarea>
    <br>
    <span style="font-size:90%">
     Enter each role name on a separate line. Reports can only be downloaded by users with access.
     <br>
     If left blank, the report can be downloaded by users with edit access.
     <br>
     Enter * to allow downloads by all users with access.
    </span>
   </td>
  </tr>
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>SQL Query</td>
   <td>
    <textarea name="sql_query" style="height:500px;max-width:95%"><?php
echo $reportData['sql_query'] ?? ''; ?></textarea>
   </td>
  </tr>
  <tr><td colspan="2">&nbsp;</td></tr>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="Save Report">
   </td>
  </tr>
 </table>
</form>
<script type="text/javascript">
 $('[name="sql_query"]')[0].onkeyup = function()
 {
   if ( this.value.toLowerCase().substring( 0, 7 ) != 'select ' )
   {
     this.setCustomValidity( 'Invalid SQL query' )
   }
   else
   {
     this.setCustomValidity( '' )
   }
 }
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';