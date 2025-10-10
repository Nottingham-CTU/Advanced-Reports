<?php
/**
 *	Accumulation Reports edit page.
 */

namespace Nottingham\AdvancedReports;

const ACC_SMART_VAR = '[accumulation-number]';



// Check user can edit accumulation reports and verify the report exists and is an accumulation
// report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'accumulation' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'accumulation' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );
$canSaveIfApi = ( ! $module->getSystemSetting( 'admin-only-api' ) ||
                  $module->getUser()->isSuperUser() );
$canSaveIfPublic = ( ! $module->getSystemSetting( 'admin-only-public' ) ||
                     $module->getUser()->isSuperUser() );



// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// Validate data
	$validationMsg = '';
	if ( ! $canSaveIfApi && $_POST['report_as_api'] == 'Y' )
	{
		$validationMsg = 'Reports with API access can only be saved by an administrator.';
	}
	if ( ! $canSaveIfPublic && $_POST['report_as_public'] == 'Y' )
	{
		$validationMsg = 'Reports with Public access can only be saved by an administrator.';
	}
	// - Check that the accumulation range settings are valid.
	if ( $validationMsg == '' )
	{
		if ( ! \LogicTester::isValid( $_POST['acc_start'] ) )
		{
			$validationMsg = 'Start of accumulation range is not valid.';
		}
		elseif ( ! \LogicTester::isValid( $_POST['acc_end'] ) )
		{
			$validationMsg = 'End of accumulation range is not valid.';
		}
		elseif ( $_POST['acc_step'] != '' &&
		         ! preg_match( '/^[0-9]+(\.[0-9]{1,2})?$/', $_POST['acc_step'] ) )
		{
			$validationMsg = 'Step of accumulation range is not valid.';
		}
	}
	// - Check the validity of accumulation and grouping logic.
	if ( $validationMsg == '' )
	{
		if ( ! \LogicTester::isValid( $module->replaceLogicVars( $_POST['acc_logic'],
		                                                         ACC_SMART_VAR, '0' ) ) )
		{
			$validationMsg = 'Accumulation logic is not valid.';
		}
		elseif ( ! \LogicTester::isValid( $module->replaceLogicVars( $_POST['group_logic'],
		                                                             ACC_SMART_VAR, '0' ) ) )
		{
			$validationMsg = 'Group by logic is not valid.';
		}
	}
	// - Check the validity of column label logic.
	if ( $validationMsg == '' )
	{
		if ( ! \LogicTester::isValid( $module->replaceLogicVars( $_POST['col_logic'],
		                                                         ACC_SMART_VAR, '0' ) ) )
		{
			$validationMsg = 'Accumulation logic is not valid.';
		}
	}
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_ACCCHK'] ) )
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
	$module->submitReportConfig( $reportID, true, [ 'saveable', 'image', 'api', 'public' ] );
	$reportData = [];
	foreach ( [ 'desc', 'acc_start', 'acc_end', 'acc_step', 'acc_logic', 'group_logic',
	            'group_total', 'group_noblank', 'display', 'col_logic', 'col_reverse' ]
	          as $reportDataVar )
	{
		if ( in_array( $reportDataVar, [ 'group_total', 'group_noblank', 'col_reverse' ] ) )
		{
			$reportData[ $reportDataVar ] = isset( $_POST[ $reportDataVar ] );
			continue;
		}
		$reportData[ $reportDataVar ] = str_replace( "\r\n", "\n", $_POST[ $reportDataVar ] );
	}
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212;
 Edit Accumulation Report: <?php echo $module->escapeHTML( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' );
?>"><i class="fas fa-arrow-circle-left fs11"></i> Back to edit reports</a>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php echo $module->getUrl( 'README-Accumulation.md' );
?>" target="_blank"><i class="fas fa-book fs11"></i> View Documentation</a>
</p>
<form method="post" id="accform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, true,
                                          [ 'saveable', 'image', 'api', 'public' ] ); ?>
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>Description</td>
   <td>
    <textarea name="desc" style="height:70px;max-width:95%;white-space:pre"><?php
echo $reportData['desc'] ?? ''; ?></textarea>
    <br>
    <span class="field-desc">
     Optional. If specified, displays this text above the report.
     Supports &lt;a&gt;, &lt;b&gt; and &lt;i&gt; HTML tags.
    </span>
   </td>
  </tr>
  <tr>
   <td>Accumulation range</td>
   <td>
    <div id="acc_err_msg" class="mod-advrep-errmsg" style="display:none;margin-top:5px">
     <i class="fas fa-exclamation-triangle"></i>
     <span></span>
    </div>
    <table style="width:95%;max-width:750px">
     <tr>
      <td style="width:6em">Start</td>
      <td><input type="text" name="acc_start" style="width:100%" required
                 value="<?php echo $module->escapeHTML( $reportData['acc_start'] ?? '' ); ?>"></td>
     </tr>
     <tr>
      <td style="width:6em">End</td>
      <td><input type="text" name="acc_end" style="width:100%" required
                 value="<?php echo $module->escapeHTML( $reportData['acc_end'] ?? '' ); ?>"></td>
     </tr>
     <tr>
      <td style="width:6em">Step</td>
      <td>
       <input type="text" name="acc_step" style="width:5em" pattern="[0-9]+(\.[0-9]{1,2})?"
              value="<?php echo $module->escapeHTML( $reportData['acc_step'] ?? '' ); ?>">
       &nbsp;<i>(default = 1)</i>
      </td>
     </tr>
    </table>
   </td>
  </tr>
  <tr>
   <td>Accumulation logic</td>
   <td>
    <textarea name="acc_logic" spellcheck="false" required
              style="height:100px;max-width:95%;font-family:monospace;white-space:pre"><?php
echo $module->escapeHTML( $reportData['acc_logic'] ?? '' ); ?></textarea>
    <br>
    <span class="field-desc">
     REDCap logic to be run for each record and accumulation number.<br>
     Use <i><?php echo ACC_SMART_VAR; ?></i> smart variable for the accumulation number.<br>
     This logic must return a numeric value.
    </span>
  </tr>
  <tr>
   <td>Group by</td>
   <td>
    <textarea name="group_logic" spellcheck="false"
              style="height:100px;max-width:95%;font-family:monospace;white-space:pre"><?php
echo $module->escapeHTML( $reportData['group_logic'] ?? '' ); ?></textarea>
    <br>
    <span class="field-desc">
     REDCap logic to be run for each record.<br>
     Records returning the same value are grouped together.
    </span>
   </td>
  </tr>
  <tr>
   <td>Display format</td>
   <td>
    <label>
     <input type="radio" name="display" value="cumul" required<?php
		echo ( $reportData['display'] ?? 'cumul' ) == 'cumul'
					? ' checked' : ''; ?>> Cumulative only
    </label>
    <br>
    <label>
     <input type="radio" name="display" value="diff" required<?php
		echo ( $reportData['display'] ?? 'cumul' ) == 'diff'
					? ' checked' : ''; ?>> Change only
    </label>
    <br>
    <label>
     <input type="radio" name="display" value="diffu" required<?php
		echo ( $reportData['display'] ?? 'cumul' ) == 'diffu'
					? ' checked' : ''; ?>> Change only (omit + sign for increase)
    </label>
    <br>
    <label>
     <input type="radio" name="display" value="cumul/diff" required<?php
		echo ( $reportData['display'] ?? 'cumul' ) == 'cumul/diff'
					? ' checked' : ''; ?>> Cumulative with change in parentheses
    </label>
   </td>
  </tr>
  <tr>
   <td>Column label logic</td>
   <td>
    <textarea name="col_logic" spellcheck="false"
              style="height:100px;max-width:95%;font-family:monospace;white-space:pre"><?php
echo $module->escapeHTML( $reportData['col_logic'] ?? '' ); ?></textarea>
    <br>
    <span class="field-desc">
     Optional. If not specified, use the accumulation numbers.
    </span>
   </td>
  </tr>
  <tr>
   <td>Display options</td>
   <td>
    <label>
     <input type="checkbox" name="group_total"<?php
echo $reportData['group_total'] ? ' checked' : ''; ?>> Add a 'Total' row
    </label>
    <br>
    <label>
     <input type="checkbox" name="group_noblank"<?php
echo $reportData['group_noblank'] ? ' checked' : ''; ?>> Exclude blank group
    </label>
    <br>
    <label>
     <input type="checkbox" name="col_reverse"<?php
echo $reportData['col_reverse'] ? ' checked' : ''; ?>> Columns in reverse order
    </label>
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
   $('#accform')[0].onsubmit = function()
   {
     if ( vValidated )
     {
       return true
     }
     $.ajax( { url : window.location.href,
               method : 'POST',
               data : $('#accform').serialize(),
               headers : { 'X-RC-AdvRep-AccChk' : '1' },
               dataType : 'json',
               success : function ( result )
               {
                 if ( result === true )
                 {
                   vValidated = true
                   $('#accform')[0].submit()
                   $('#acc_err_msg').css( 'display', 'none' )
                 }
                 else
                 {
                   var vMsg = result
                   $('#acc_err_msg span').text( vMsg )
                   $('#acc_err_msg').css( 'display', '' )
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
