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
	if ( str_replace( [ "\r", "\n" ], ' ',
	                  substr( strtolower( $_POST['sql_query'] ), 0, 7 ) ) != 'select ' )
	{
		exit;
	}

	// Save data
	$module->submitReportConfig( $reportID );
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
<?php $module->outputReportConfigOptions( $reportConfig ); ?>
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
   if ( this.value.toLowerCase().substring( 0, 7 ).replace( /[\r\n]/, ' ' ) != 'select ' )
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
