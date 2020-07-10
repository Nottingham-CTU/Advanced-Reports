<?php
/**
 *	Advanced reports edit page.
 */



// Check user can edit reports, redirect to main reports page if not.
if ( ! $module->isReportEditable() )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}



// Handle form submissions.
if ( ! empty( $_POST ) && isset( $_POST['action'] ) )
{
	// Add new report.
	if ( $_POST['action'] == 'add_report' )
	{
		if ( $_POST['report_id'] == '' || $_POST['report_label'] == '' ||
		     $_POST['report_type'] == '' )
		{
			echo 'Error: Required field missing.';
			exit;
		}
		if ( ! $module->isReportEditable( $_POST['report_type'] ) )
		{
			echo 'Error: Cannot create selected report type.';
			exit;
		}
		$module->addReport( $_POST['report_id'], $_POST['report_type'], $_POST['report_label'] );
		header( 'Location: ' . $module->getUrl( $_POST['report_type'] . '_edit.php?report_id=' .
		                                        $_POST['report_id'] ) );
		exit;
	}

	// Delete a report.
	if ( $_POST['action'] == 'delete_report' )
	{
		if ( $_POST['report_id'] == '' )
		{
			echo 'Error: Required field missing.';
			exit;
		}
		if ( ! $module->isReportEditable(
		                             $module->getReportConfig( $_POST['report_id'], 'type' ) ) )
		{
			echo 'Error: Cannot delete selected report type.';
			exit;
		}
		$module->deleteReport( $_POST['report_id'] );
		header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
		exit;
	}
}



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit
</div>
<form method="post">
 <table class="mod-advrep-formtable">
  <tr><th colspan="2">Add Report</th></tr>
  <tr>
   <td>Unique Report Name</td>
   <td>
    <input type="text" name="report_id" required placeholder="e.g. my_report"
           pattern="[a-z0-9_-]+" title="lowercase letters, numbers, dashes and underscores">
   </td>
  </tr>
  <tr>
   <td>Report Label</td>
   <td>
    <input type="text" name="report_label" required placeholder="e.g. My Report">
   </td>
  </tr>
  <tr>
   <td>Report Type</td>
   <td>
    <select name="report_type" required>
     <option value="">[Select...]</option>
<?php
if ( $module->isReportEditable( 'sql' ) )
{
?>
     <option value="sql">SQL</option>
<?php
}
?>
    </select>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="hidden" name="action" value="add_report">
    <input type="submit" value="Add Report">
   </td>
  </tr>
 </table>
</form>
<?php
$listReports = $module->getReportList();
if ( count( $listReports ) > 0 )
{
?>
<p>&nbsp;</p>
<table class="mod-advrep-datatable" style="width:97%">
 <tr>
  <th colspan="4" style="font-size:130%;text-align:left;border-right-width:1px">Edit Report</th>
 </tr>
<?php
	foreach ( $listReports as $reportID => $infoReport )
	{
?>
 <tr>
  <td style="text-align:left">
   <span style="font-size:115%">
    <?php echo htmlspecialchars( $infoReport['label'] ), "\n"; ?>
   </span>
   <br>
   <span style="font-size:90%">
    <b>Name:</b> <?php echo $reportID; ?> &nbsp;|&nbsp;
    <b>Type:</b> <?php echo $infoReport['type']; ?> &nbsp;|&nbsp;
    <b>Category:</b> <?php echo $infoReport['category'] ?? '<i>(none)</i>'; ?> &nbsp;|&nbsp;
    <b>Visibility:</b> <?php echo $infoReport['visible'] ? 'visible' : 'hidden', "\n"; ?>
   </span>
  </td>
  <td style="width:80px">
<?php
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
?>
   <a href="<?php echo $module->getUrl( $infoReport['type'] . '_view.php?report_id=' . $reportID );
?>" class="far fa-file-alt fs12"> View</a>
<?php
		}
?>
  </td>
  <td style="width:90px">
<?php
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
?>
   <a href="<?php echo $module->getUrl( $infoReport['type'] . '_edit.php?report_id=' . $reportID );
?>" class="fas fa-pencil-alt fs12"> Edit</a>
<?php
		}
?>
  </td>
  <td style="width:90px">
<?php
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
?>
   <a href="" class="fas fa-trash fs12" onclick="return mod_advrep_delete( '<?php
			echo $reportID, "', '";
			echo addslashes( htmlspecialchars( $infoReport['label'] ) ); ?>')"> Delete</a>
   <form method="post" id="delreport_<?php echo $reportID; ?>">
    <input type="hidden" name="action" value="delete_report">
    <input type="hidden" name="report_id" value="<?php echo $reportID; ?>">
   </form>
<?php
		}
?>
  </td>
 </tr>
<?php
	}
?>
</table>
<script type="text/javascript">
 function mod_advrep_delete( id, label )
 {
   if ( confirm( 'Are you sure you want to delete this report?\n\n  ' + label ) )
   {
     $('#delreport_' + id)[0].submit()
   }
   return false
 }
</script>
<?php
}

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';