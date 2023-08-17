<?php
/**
 *	Record Table Reports edit page.
 */

namespace Nottingham\AdvancedReports;



// Check user can edit Record Table reports and verify the report exists and is a Record Table
// report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'recordtbl' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'recordtbl' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );



$listInstruments = \REDCap::getInstrumentNames();
$listEventUniq = \REDCap::getEventNames( true );
$listEventName = \REDCap::getEventNames( false, true );
$listEvents = [];
foreach ( $listEventUniq as $eventID => $eventUniq )
{
	$listEvents[ $eventUniq ] = $listEventName[ $eventID ];
}



// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// TODO: Validate data
	$validationMsg = '';
	// - Check the forms/fields are specified if an alias or join condition is specified.

	if ( ! isset( $_POST['rectbl_forms'] ) )
	{
		$validationMsg = 'At least one instrument must be selected.';
	}
	if ( $validationMsg == '' && ! isset( $_POST['rectbl_events'] ) )
	{
		$validationMsg = 'At least one event must be selected.';
	}
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_RECORDTBLCHK'] ) )
	{
		header( 'Content-Type: application/json' );
		if ( $validationMsg == '' )
		{
			echo 'true';
		}
		else
		{
			echo json_encode( $validationMsg );
		}
		exit;
	}
	if ( $validationMsg != '' )
	{
		exit;
	}

	// Save data
	$module->submitReportConfig( $reportID, true, 'image' );
	if ( count( $_POST['rectbl_forms'] ) == count( $listInstruments ) )
	{
		$_POST['rectbl_forms'] = [];
	}
	if ( count( $_POST['rectbl_events'] ) == count( $listEvents ) )
	{
		$_POST['rectbl_events'] = [];
	}
	$reportData = [ 'desc' => $_POST['rectbl_desc'], 'forms' => $_POST['rectbl_forms'],
	                'events' => $_POST['rectbl_events'],
	                'nomissingdatacodes' => isset( $_POST['query_nomissingdatacodes'] ) ];
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}




// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit Record Table Report: <?php
echo $module->escapeHTML( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' )
?>" class=""><i class="fas fa-arrow-circle-left fs11"></i> Back to edit reports</a>
</p>
<form method="post" id="rectblform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, true, 'image' ); ?>
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>Description</td>
   <td>
    <textarea name="rectbl_desc" style="height:70px;max-width:95%;white-space:pre"><?php
echo $reportData['desc'] ?? ''; ?></textarea>
    <br>
    <span class="field-desc">
     Optional. If specified, displays this text above the report.
     Supports &lt;a&gt;, &lt;b&gt; and &lt;i&gt; HTML tags.
    </span>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <div id="rectbl_err_msg" class="mod-advrep-errmsg" style="display:none;margin-top:5px">
     <i class="fas fa-exclamation-triangle"></i>
     <span></span>
    </div>
   </td>
  </tr>
  <tr>
   <td>Instruments</td>
   <td>
<?php
foreach ( $listInstruments as $instrumentUniq => $instrumentName )
{
	$instrumentAttr = 'value="' . $instrumentUniq . '"';
	if ( ! isset( $reportData['forms'] ) || empty( $reportData['forms'] ) ||
	     in_array( $instrumentUniq, $reportData['forms'] ) )
	{
		$instrumentAttr .= ' checked';
	}
?>
    <input type="checkbox" name="rectbl_forms[]" <?php echo $instrumentAttr; ?>>
    <?php echo $instrumentName; ?><br>
<?php
}
?>
   </td>
  </tr>
  <tr>
   <td>Events</td>
   <td>
<?php
foreach ( $listEvents as $eventUniq => $eventName )
{
	$eventAttr = 'value="' . $eventUniq . '"';
	if ( ! isset( $reportData['events'] ) || empty( $reportData['events'] ) ||
	     in_array( $eventUniq, $reportData['events'] ) )
	{
		$eventAttr .= ' checked';
	}
?>
    <input type="checkbox" name="rectbl_events[]" <?php echo $eventAttr; ?>>
    <?php echo $eventName; ?><br>
<?php
}
?>
   </td>
  </tr>
  <tr>
   <td>Hide missing data codes</td>
   <td>
    <input type="checkbox" name="query_nomissingdatacodes"<?php
echo $reportData['nomissingdatacodes'] ? ' checked' : '';
?>>
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
 (function ()
 {
   var vValidated = false
   $('#rectblform')[0].onsubmit = function()
   {
     if ( vValidated )
     {
       return true
     }
     $.ajax( { url : '<?php echo $module->getUrl( 'recordtbl_edit.php?report_id=' . $reportID ); ?>',
               method : 'POST',
               data : $('#rectblform').serialize(),
                        headers : { 'X-RC-AdvRep-RecordTblChk' : '1' },
                        dataType : 'json',
                        success : function ( result )
                        {
                          if ( result === true )
                          {
                            vValidated = true
                            $('#rectblform')[0].submit()
                            $('#rectbl_err_msg').css( 'display', 'none' )
                          }
                          else
                          {
                            var vMsg = 'Invalid record table: ' + result
                            $('#rectbl_err_msg span').text( vMsg )
                            $('#rectbl_err_msg').css( 'display', '' )
                          }
                        }
             } )
     return false
   }
 })()
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
