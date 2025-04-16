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
			echo $module->tt('error_req_field_missing');
			exit;
		}
		if ( preg_match( '/[^a-z0-9_-]/', $_POST['report_id'] ) )
		{
			echo $module->tt('error_invalid_report_id');
			exit;
		}
		if ( ! $module->isReportEditable( $_POST['report_type'] ) )
		{
			echo $module->tt('error_cannot_create_type');
			exit;
		}
		$listReports = $module->getReportList();
		if ( isset( $listReports[ $_POST['report_id'] ] ) )
		{
			echo $module->tt('error_report_id_in_use');
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
			echo $module->tt('error_req_field_missing');
			exit;
		}
		if ( preg_match( '/[^a-z0-9_-]/', $_POST['new_report_id'] ) )
		{
			echo $module->tt('error_invalid_report_id');
			exit;
		}
		$reportConfig = $module->getReportConfig( $_POST['report_id'] );
		if ( ! $module->isReportEditable( $reportConfig['type'] ) )
		{
			echo $module->tt('error_cannot_copy_type');
			exit;
		}
		$listReports = $module->getReportList();
		if ( ! isset( $listReports[ $_POST['report_id'] ] ) )
		{
			echo $module->tt('error_report_not_exist');
			exit;
		}
		if ( isset( $listReports[ $_POST['new_report_id'] ] ) )
		{
			echo $module->tt('error_report_id_in_use');
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
			echo $module->tt('error_req_field_missing');
			exit;
		}
		if ( ! $module->isReportEditable(
		                             $module->getReportConfig( $_POST['report_id'], 'type' ) ) )
		{
			echo $module->tt('error_cannot_delete_type');
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
 <?php echo $module->tt('advanced_reports_edit'), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports.php' ) ?>">
  <i class="fas fa-arrow-circle-left fs11"></i>
  <?php echo $module->tt('back_to_advanced_reports'), "\n"; ?>
 </a>
</p>
<form method="post">
 <table class="mod-advrep-formtable">
  <tr><th colspan="2"><?php echo $module->tt('add_report'); ?></th></tr>
  <tr>
   <td><?php echo $module->tt('report_id'); ?></td>
   <td>
    <input type="text" name="report_id" required
           placeholder="<?php echo $module->tt('report_id_ph'); ?>"
           pattern="[a-z0-9_\-]+" title="<?php echo $module->tt('report_id_tt'); ?>">
   </td>
  </tr>
  <tr>
   <td><?php echo $module->tt('report_label'); ?></td>
   <td>
    <input type="text" name="report_label" required
           placeholder="<?php echo $module->tt('report_label_ph'); ?>">
   </td>
  </tr>
  <tr>
   <td><?php echo $module->tt('report_type'); ?></td>
   <td>
    <select name="report_type" required>
     <option value=""><?php echo $module->tt('opt_select'); ?></option>
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
    <input type="submit" value="<?php echo $module->tt('add_report'); ?>">
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
  <th colspan="5" style="font-size:130%"><?php echo $module->tt('edit_report'); ?></th>
 </tr>
<?php
	foreach ( $listReports as $reportID => $infoReport )
	{
		$canEdit = $module->isReportEditable( $infoReport['type'] );
		if ( ! isset( $lastCategory ) || $infoReport['category'] != $lastCategory )
		{
?>
 <tr>
  <td colspan="5" style="text-align:left;font-size:0.95em;font-weight:bold">
   <?php echo $infoReport['category'] ?? ( '<i>(' . $module->tt('no_category') . ')</i>' ), "\n"; ?>
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
    <b><?php echo $module->tt('name'); ?>:</b> <?php echo $reportID; ?> &nbsp;|&nbsp;
    <b><?php echo $module->tt('type'); ?>:</b>
    <?php echo $module->getReportTypes()[ $infoReport['type'] ]; ?> &nbsp;|&nbsp;
<?php
		echo '    ';
		echo '<i class="far fa-eye', ( $infoReport['visible'] ? '' : '-slash' ), '" title="';
		echo $module->tt( 'report_' . ( $infoReport['visible'] ? 'visible' : 'hidden' ) ), '"></i>';
		if ( isset( $infoReport['download'] ) && $infoReport['download'] )
		{
			echo ' &nbsp;<i class="fas fa-download" title="', $module->tt('downloadable'), '"></i>';
		}
		if ( isset( $infoReport['saveable'] ) && $infoReport['saveable'] )
		{
			echo ' &nbsp;<i class="far fa-floppy-disk" title="', $module->tt('saveable'), '"></i>';
		}
		if ( isset( $infoReport['as_image'] ) && $infoReport['as_image'] )
		{
			echo ' &nbsp;<i class="far fa-file-image" title="', $module->tt('ret_as_image'), '"></i>';
		}
		if ( isset( $infoReport['as_api'] ) && $infoReport['as_api'] )
		{
			echo ' &nbsp;<i class="fas fa-laptop-code" title="', $module->tt('api'), '"></i>';
		}
		if ( isset( $infoReport['as_public'] ) && $infoReport['as_public'] )
		{
			echo ' &nbsp;<i class="fas fa-earth-americas" title="', $module->tt('public'), '"></i>';
		}
		echo "\n";
		if ( isset( $infoReport['lastupdated_user'] ) )
		{
?>
    <br>
    <?php echo $module->tt( 'last_updated_ut', $infoReport['lastupdated_user'],
                            $module->formatDate( date( 'Y-m-d H:i',
                                                       $infoReport['lastupdated_time'] ), 'upf' ) .
                            date( ' (T)', $infoReport['lastupdated_time'] ) ), "\n"; ?>
<?php
		}
?>
   </span>
  </td>
  <td style="width:75px;text-align:center">
<?php
		if ( $canEdit )
		{
?>
   <a href="<?php echo $module->getUrl( 'view.php?report_id=' . $reportID );
?>" class="fs12"><i class="far fa-file-alt fs14"></i> <?php echo $module->tt('view'); ?></a>
<?php
		}
?>
  </td>
  <td style="width:75px;text-align:center">
<?php
		if ( $canEdit )
		{
?>
   <a href="<?php echo $module->getUrl( $infoReport['type'] . '_edit.php?report_id=' . $reportID );
?>" class="fs12"><i class="fas fa-pencil-alt fs14"></i> <?php echo $module->tt('edit'); ?></a>
<?php
		}
?>
  </td>
  <td style="width:75px;text-align:center">
<?php
		if ( $canEdit )
		{
?>
   <a href="" class="fs12" data-copy-report="<?php echo $module->escape( $reportID ); ?>"
      data-report-label="<?php echo $module->escape( $infoReport['label'] );
?>"><i class="fas fa-copy fs14"></i> <?php echo $module->tt('copy'); ?></a>
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
		if ( $canEdit )
		{
?>
   <a href="" class="fs12" data-delete-report="<?php echo $module->escape( $reportID ); ?>"
      style="color:#b00" data-report-label="<?php echo $module->escape( $infoReport['label'] );
?>"><i class="fas fa-trash fs14"></i> <?php echo $module->tt('delete'); ?></a>
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
<p>&nbsp;</p>
<ul>
<?php
if ( ! empty( $listReports ) )
{
?>
 <li>
  <a id="exportLink" href="<?php echo $module->getUrl( 'export_reports.php' ) ?>"><?php
	echo $module->tt('export_reports'); ?></a>
 </li>
<?php
}
?>
 <li>
  <a href="<?php echo $module->getUrl( 'import_reports.php' ) ?>"><?php
	echo $module->tt('import_reports'); ?></a>
 </li>
</ul>
<p>&nbsp;</p>
<div id="exportDialog" style="display:none">
 <p style="padding-left:1rem;margin-bottom:15px">
  <button type="button" class="btn btn-defaultrc btn-xs"
          onclick="window.location.href='<?php echo $module->getUrl( 'export_reports.php' ) ?>'">
   <?php echo $module->tt('export_reports_all'), "\n"; ?>
  </button>
 </p>
 <p style="padding-left:1rem;margin-bottom:3px">
  <?php echo $module->tt('export_reports_all'); ?>:
 </p>
 <div style="overflow-y:auto;max-height:300px">
  <ul>
<?php
	foreach ( $listReports as $reportID => $infoReport )
	{
		if ( $module->isReportEditable( $infoReport['type'] ) )
		{
			echo '   <li><a href="', $module->getUrl( 'export_reports.php?report_id=' . $reportID ),
			     '">', $module->escape( $infoReport['label'] ), "</a></li>\n";
		}
	}
?>
  </ul>
 </div>
</div>
<script type="text/javascript">
 $(function()
 {
   $('a[data-copy-report]').on('click',function(ev)
   {
     ev.preventDefault()
     var vID = $(this).attr('data-copy-report')
     var vLabel = $(this).attr('data-report-label')
     var vDialog = <?php echo $module->escapeJSString( $module->tt('copy_report_id') ), "\n"; ?>
     vDialog = vDialog.replace( '{0}', $('<i></i>').text( vLabel ).prop('outerHTML') )
     vDialog = '<div>' + vDialog + '<br><br>' +
               <?php echo $module->escapeJSString( $module->tt('report_id_new') ); ?> +
               ': <input id="report_id_new" type="text" style="width:95%" placeholder="' +
               <?php echo $module->escapeJSString( $module->tt('report_id_ph') ); ?> +
               '" required pattern="[a-z0-9_\\-]+" title="' +
               <?php echo $module->escapeJSString( $module->tt('report_id_tt') ); ?> +
               '"></div>'
     var vSubmitFunc = function()
     {
       if ( $('#report_id_new').prop('validity').valid )
       {
         $('#copyreport_' + vID).find('[name="new_report_id"]').val( $('#report_id_new').val() )
         $('#copyreport_' + vID)[0].submit()
         simpleDialogAlt( <?php echo $module->escapeJSString( $module->tt('please_wait') ); ?>,
                          10000 )
       }
     }
     simpleDialog( vDialog,
                   <?php echo $module->escapeJSString( $module->tt('copy_report') ); ?>, null, 350,
                   null, null, vSubmitFunc )
     $('#report_id_new').trigger('focus')
   })
   $('a[data-delete-report]').on('click',function(ev)
   {
     ev.preventDefault()
     var vID = $(this).attr('data-delete-report')
     var vLabel = $(this).attr('data-report-label')
     var vDialog = <?php echo $module->escapeJSString( $module->tt('delete_report_id') ), "\n"; ?>
     vDialog = vDialog.replace( '{0}', $('<i></i>').text( vLabel ).prop('outerHTML') )
     vDialog = '<div>' + vDialog + '</div>'
     var vSubmitFunc = function()
     {
       $('#delreport_' + vID)[0].submit()
         simpleDialogAlt( <?php echo $module->escapeJSString( $module->tt('please_wait') ); ?>,
                          10000 )
     }
     simpleDialog( vDialog,
                   <?php echo $module->escapeJSString( $module->tt('delete_report') ); ?>, null,
                   350, null, null, vSubmitFunc )
   })
   $('#exportLink').on('click',function( ev )
   {
     ev.preventDefault()
     simpleDialog( null, <?php echo $module->escapeJSString( $module->tt('export_reports') ); ?>,
                   'exportDialog', 350 )
   })
 })
</script>
<?php
}

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
