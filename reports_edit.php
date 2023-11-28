<?php
/**
 *	Advanced reports edit page.
 */

namespace Nottingham\AdvancedReports;



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
		if ( preg_match( '/[^a-z0-9_-]/', $_POST['report_id'] ) )
		{
			echo 'Error: Invalid unique report name.';
			exit;
		}
		if ( ! $module->isReportEditable( $_POST['report_type'] ) )
		{
			echo 'Error: Cannot create selected report type.';
			exit;
		}
		$listReports = $module->getReportList();
		if ( isset( $listReports[ $_POST['report_id'] ] ) )
		{
			echo 'Error: Unique report name already in use.';
			exit;
		}
		$module->addReport( $_POST['report_id'], $_POST['report_type'], $_POST['report_label'] );
		header( 'Location: ' . $module->getUrl( $_POST['report_type'] . '_edit.php?report_id=' .
		                                        $_POST['report_id'] ) );
		exit;
	}

	// Copy a report.
	if ( $_POST['action'] == 'copy_report' )
	{
		if ( $_POST['report_id'] == '' || $_POST['new_report_id'] == '' )
		{
			echo 'Error: Required field missing.';
			exit;
		}
		if ( preg_match( '/[^a-z0-9_-]/', $_POST['new_report_id'] ) )
		{
			echo 'Error: Invalid unique report name.';
			exit;
		}
		$reportConfig = $module->getReportConfig( $_POST['report_id'] );
		if ( ! $module->isReportEditable( $reportConfig['type'] ) )
		{
			echo 'Error: Cannot copy selected report type.';
			exit;
		}
		$listReports = $module->getReportList();
		if ( ! isset( $listReports[ $_POST['report_id'] ] ) )
		{
			echo 'Error: Report does not exist.';
			exit;
		}
		if ( isset( $listReports[ $_POST['new_report_id'] ] ) )
		{
			echo 'Error: Unique report name already in use.';
			exit;
		}
		$module->addReport( $_POST['new_report_id'],
		                    $reportConfig['type'], $reportConfig['label'] );
		unset( $reportConfig['type'], $reportConfig['label'], $reportConfig['visible'],
		       $reportConfig['lastupdated_user'], $reportConfig['lastupdated_time'] );
		if ( isset( $reportConfig['as_api'] ) || isset( $reportConfig['api_key'] ) )
		{
			$reportConfig['as_api'] = false;
			$reportConfig['api_key'] = '';
		}
		foreach ( $reportConfig as $configName => $configValue )
		{
			$module->setReportConfig( $_POST['new_report_id'], $configName, $configValue );
		}
		$module->setReportData( $_POST['new_report_id'],
		                        $module->getReportData( $_POST['report_id'] ) );
		header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
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



// Clean up report data for deleted projects.
$moduleName = preg_replace( '_v[0-9.]+$', '', $module->getModuleDirectoryName() );
$module->query( 'DELETE FROM redcap_external_module_settings ' .
                'WHERE external_module_id = (SELECT external_module_id FROM' .
                ' redcap_external_modules WHERE directory_prefix = ?) AND project_id IS NULL ' .
                "AND `key` REGEXP '^p[1-9][0-9]*-' AND `key` NOT REGEXP concat('^p(', " .
                "(SELECT group_concat(project_id SEPARATOR '|') FROM redcap_projects), ')-') " .
                'LIMIT 50', [ $moduleName ] );



// Get and sort the list of reports.
$listReports = $module->getReportList();
uasort( $listReports, [ $module, 'sortReports' ] );



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports.php' )
?>"><i class="fas fa-arrow-circle-left fs11"></i> Back to advanced reports</a>
</p>
<form method="post">
 <table class="mod-advrep-formtable">
  <tr><th colspan="2">Add Report</th></tr>
  <tr>
   <td>Unique Report Name</td>
   <td>
    <input type="text" name="report_id" required placeholder="e.g. my_report"
           pattern="[a-z0-9_\-]+" title="lowercase letters, numbers, dashes and underscores">
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
foreach ( $module->getReportTypes() as $typeCode => $typeName )
{
	if ( $module->isReportEditable( $typeCode ) )
	{
?>
     <option value="<?php echo $typeCode; ?>"><?php echo htmlspecialchars( $typeName ); ?></option>
<?php
	}
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
if ( count( $listReports ) > 0 )
{
?>
<p>&nbsp;</p>
<table class="mod-advrep-listtable" style="width:97%">
 <tr>
  <th colspan="5" style="font-size:130%">Edit Report</th>
 </tr>
<?php
	foreach ( $listReports as $reportID => $infoReport )
	{
		if ( ! isset( $lastCategory ) || $infoReport['category'] != $lastCategory )
		{
?>
 <tr>
  <td colspan="5" style="text-align:left;font-size:0.95em;font-weight:bold">
   <?php echo $infoReport['category'] ?? '<i>(no category)</i>', "\n"; ?>
  </td>
 </tr>
<?php
			$lastCategory = $infoReport['category'] ?? '';
		}
?>
 <tr>
  <td style="text-align:left;padding-left:8px">
   <span style="font-size:1.2em">
    <?php echo htmlspecialchars( $infoReport['label'] ), "\n"; ?>
   </span>
   <br>
   <span style="font-size:90%">
    <b>Name:</b> <?php echo $reportID; ?> &nbsp;|&nbsp;
    <b>Type:</b> <?php echo $module->getReportTypes()[ $infoReport['type'] ]; ?> &nbsp;|&nbsp;
    <b>Visibility:</b> <?php echo $infoReport['visible'] ? 'visible' : 'hidden', "\n"; ?>
<?php
		if ( isset( $infoReport['as_api'] ) && $infoReport['as_api'] == 'Y' &&
		     $infoReport['api_key'] != '' )
		{
?>
    &nbsp;|&nbsp; <b>API</b>
<?php
		}
		if ( isset( $infoReport['lastupdated_user'] ) )
		{
?>
    <br>
    <b>Last updated by</b> <?php echo htmlspecialchars( $infoReport['lastupdated_user'] ), "\n"; ?>
    &nbsp;<b>at</b> <?php echo date( 'Y-m-d H:i (T)', $infoReport['lastupdated_time'] ), "\n"; ?>
<?php
		}
?>
   </span>
  </td>
  <td style="width:75px;text-align:center">
<?php
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
?>
   <a href="<?php echo $module->getUrl( $infoReport['type'] . '_view.php?report_id=' . $reportID );
?>" class="fs12"><i class="far fa-file-alt fs14"></i> View</a>
<?php
		}
?>
  </td>
  <td style="width:75px;text-align:center">
<?php
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
?>
   <a href="<?php echo $module->getUrl( $infoReport['type'] . '_edit.php?report_id=' . $reportID );
?>" class="fs12"><i class="fas fa-pencil-alt fs14"></i> Edit</a>
<?php
		}
?>
  </td>
  <td style="width:75px;text-align:center">
<?php
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
?>
   <a href="" class="fs12" onclick="return mod_advrep_copy( '<?php
			echo $reportID, "', '";
			echo addslashes( htmlspecialchars( $infoReport['label'] ) );
?>')"><i class="fas fa-copy fs14"></i> Copy</a>
   <form method="post" id="copyreport_<?php echo $reportID; ?>">
    <input type="hidden" name="action" value="copy_report">
    <input type="hidden" name="report_id" value="<?php echo $reportID; ?>">
    <input type="hidden" name="new_report_id" value="">
   </form>
<?php
		}
?>
  </td>
  <td style="width:85px;text-align:center">
<?php
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
?>
   <a href="" class="fs12" style="color:#b00" onclick="return mod_advrep_delete( '<?php
			echo $reportID, "', '";
			echo addslashes( htmlspecialchars( $infoReport['label'] ) );
?>')"><i class="fas fa-trash fs14"></i> Delete</a>
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
 $(function(){
   var vDialog = $('<div>Copy report <i></i>?<br><br>New unique report name: ' +
                   '<input type="text" style="width:95%" placeholder="e.g. my_report" ' +
                   'required pattern="[a-z0-9_\\-]+" title="lowercase letters, numbers, dashes ' +
                   'and underscores"></div>')
   var vCopyID = ''
   vDialog.dialog(
   {
     autoOpen:false,
     buttons:
     {
       OK : function()
       {
         if ( vDialog.find('input').prop('validity').valid )
         {
           $('#copyreport_' + vCopyID).find('[name="new_report_id"]')
                                      .val( vDialog.find('input').val() )
           vDialog.dialog('close')
           $('#copyreport_' + vCopyID)[0].submit()
         }
       },
       Cancel : function() { vDialog.dialog('close') }
     },
     modal:true,
     resizable:false,
     title:'Copy Report',
     width:350
   })
   window.mod_advrep_copy = function( id, label )
   {
     vCopyID = id
     vDialog.find('i').text(label)
     vDialog.find('input').val('')
     vDialog.dialog('open')
     return false
   }
 })
 $(function(){
   var vDialog = $('<div>Are you sure you want to delete the report <i></i>?</div>')
   var vDelID = ''
   vDialog.dialog(
   {
     autoOpen:false,
     buttons:
     {
       OK : function() { vDialog.dialog('close'); $('#delreport_' + vDelID)[0].submit() },
       Cancel : function() { vDialog.dialog('close') }
     },
     modal:true,
     resizable:false,
     title:'Delete Report',
     width:350
   })
   window.mod_advrep_delete = function( id, label )
   {
     vDelID = id
     vDialog.find('i').text(label)
     vDialog.dialog('open')
     return false
   }
 })
</script>
<?php
}

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
