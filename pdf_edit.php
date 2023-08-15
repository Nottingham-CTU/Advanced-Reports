<?php
/**
 *	PDF Reports edit page.
 */

namespace Nottingham\AdvancedReports;



// Check user can edit PDF reports and verify the report exists and is an PDF report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'pdf' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'pdf' )
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
	$validationMsg = '';
	// - Check the forms/fields are specified if an alias, condition or sort logic is specified.
	foreach ( $_POST['query_form'] as $i => $formName )
	{
		if ( $formName == '' &&
		     ( $_POST['query_form_alias'][$i] != '' ||
		       $_POST['query_where'][$i] != '' ||
		       $_POST['query_orderby'][$i] != '' ) )
		{
			$validationMsg =
					'Form cannot be empty if alias or condition/sorting logic specified.';
			break;
		}
	}
	// - Check the validity of forms condition and sorting logic.
	if ( $validationMsg == '' )
	{
		try
		{
			foreach ( $_POST['query_where'] as $formCond )
			{
				if ( $formCond != '' )
				$module->parseLogic( $formCond, false, false, false );
			}
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in form condition logic - ' . $e->getMessage();
		}
	}
	if ( $validationMsg == '' )
	{
		try
		{
			foreach ( $_POST['query_orderby'] as $formCond )
			{
				if ( $formCond != '' )
				$module->parseLogic( $formCond, false, false, false );
			}
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in form sorting logic - ' . $e->getMessage();
		}
	}
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_PDFQUERYCHK'] ) )
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
	$reportData = [ 'forms' => [], 'pdf' => $_POST['pdf'] ];
	foreach ( $_POST['query_form'] as $i => $formName )
	{
		if ( $formName == '' )
		{
			continue;
		}
		$reportData['forms'][] = [ 'form' => $formName, 'alias' => $_POST['query_form_alias'][$i],
		                           'where' => $_POST['query_where'][$i],
		                           'orderby' => $_POST['query_orderby'][$i] ];
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
 Advanced Reports &#8212; Edit PDF Report: <?php
echo $module->escapeHTML( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' );
?>" class=""><i class="fas fa-arrow-circle-left fs11"></i> Back to edit reports</a>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php echo $module->getUrl( 'README-PDF.md' );
?>" target="_blank"><i class="fas fa-book fs11"></i> View Documentation</a>
</p>
<form method="post" id="queryform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, false ); ?>
  <tr><th colspan="2">Report Definition - Data Query</th></tr>
  <tr>
   <td></td>
   <td>
    <div id="query_err_msg" class="mod-advrep-errmsg" style="display:none;margin-top:5px">
     <i class="fas fa-exclamation-triangle"></i>
     <span></span>
    </div>
   </td>
  </tr>
  <tr>
   <td>Instruments</td>
   <td>
    <table id="inst-entries-tbl" style="width:95%;max-width:550px">
<?php
if ( empty( $reportData['forms'] ) )
{
	$reportData['forms'] = [ [ 'form' => '', 'alias' => '', 'where' => '', 'orderby' => '' ] ];
}
foreach ( $reportData['forms'] as $formData )
{
?>
     <tr>
      <td style="text-align:left;width:60px">&#8226;&nbsp;Instrument:</td>
      <td style="text-align:left;width:60px"><?php
	$module->outputInstrumentDropdown( 'query_form[]', $formData['form'] );
?></td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_form_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escapeHTML( $formData['alias'] ); ?>" style="width:100%">
      </td>
     </tr>
     <tr>
      <td style="text-align:left;width:unset">&nbsp;&nbsp;&nbsp;Condition:</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_where[]" placeholder="condition logic" style="width:100%"
              value="<?php echo $module->escapeHTML( $formData['where'] ); ?>">
      </td>
     </tr>
     <tr>
      <td style="text-align:left;width:unset">&nbsp;&nbsp;&nbsp;Sorting:</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_orderby[]" style="width:100%" placeholder="sorting logic"
              list="field-var-list-sort"
              value="<?php echo $module->escapeHTML( $formData['orderby'] ?? '' ); ?>">
      </td>
     </tr>
<?php
}
?>
     <tr style="display:none">
      <td style="text-align:left;width:unset">&#8226;&nbsp;Instrument:</td>
      <td style="text-align:left;width:unset"><?php
$module->outputInstrumentDropdown( 'query_form[]', '' );
?></td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_form_alias[]" placeholder="alias (optional)"
              style="width:100%">
      </td>
     </tr>
     <tr style="display:none">
      <td style="text-align:left;width:unset">&nbsp;&nbsp;&nbsp;Condition:</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_where[]" placeholder="condition logic" style="width:100%">
      </td>
     </tr>
     <tr style="display:none">
      <td style="text-align:left;width:unset">&nbsp;&nbsp;&nbsp;Sorting:</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_orderby[]" style="width:100%" placeholder="sorting logic"
              list="field-var-list-sort"
              value="<?php echo $module->escapeHTML( $formData['orderby'] ?? '' ); ?>">
      </td>
     </tr>
    </table>
    <span id="inst-entries-link" style="display:none">
     <a onclick="$('#inst-entries-tbl tr').slice(-3).clone(true).css('display',''
                    ).insertBefore($('#inst-entries-tbl tr').slice(-3,-2));return false"
        href="#" class=""><i class="fas fa-plus-circle fs12"></i> Add instrument</a>
    </span>
   </td>
  </tr>
  <tr><th colspan="2">Report Definition - PDF Template</th></tr>
  <tr>
   <td>HTML Source</td>
   <td>
    <textarea name="pdf" style="height:600px;max-width:95%;white-space:pre"><?php
echo $reportData['pdf'] ?? ''; ?></textarea>
    <br>
    <span class="field-desc">
     The PDF will be rendered based on this HTML source.
    </span>
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
<datalist id="field-var-list"></datalist>
<datalist id="field-var-list-sort"></datalist>
<script type="text/javascript">
 (function ()
 {
   $('#inst-entries-link').css('display','')
   var vValidated = false
   $('#queryform')[0].onsubmit = function()
   {
     if ( vValidated )
     {
       return true
     }
     $.ajax( { url : '<?php echo $module->getUrl( 'pdf_edit.php?report_id=' . $reportID ); ?>',
               method : 'POST',
               data : $('#queryform').serialize(),
                        headers : { 'X-RC-AdvRep-PDFQueryChk' : '1' },
                        dataType : 'json',
                        success : function ( result )
                        {
                          if ( result === true )
                          {
                            vValidated = true
                            $('#queryform')[0].submit()
                            $('#query_err_msg').css( 'display', 'none' )
                          }
                          else
                          {
                            var vMsg = 'Invalid data query: ' + result
                            $('#query_err_msg span').text( vMsg )
                            $('#query_err_msg').css( 'display', '' )
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
