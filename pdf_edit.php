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



// Get the reports which can be used as a data source for this report.
$listDataSources = [];
foreach ( $listReports as $rID => $rConfig )
{
	if ( $rConfig['type'] == 'instrument' )
	{
		$listDataSources[ $rID ] = $rConfig['label'];
	}
}



// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// Validate data
	$validationMsg = '';
	// - Check the data source is specified and is valid.
	if ( $validationMsg == '' )
	{
		if ( ! isset( $listDataSources[ $_POST['source'] ] ) )
		{
			$validationMsg = 'A data source has not been selected.';
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
	$module->submitReportConfig( $reportID, false );
	$reportData = [ 'source' => $_POST['source'], 'pdf' => $_POST['pdf'],
	                'pdf_size' => $_POST['pdf_size'],
	                'pdf_orientation' => $_POST['pdf_orientation'] ];
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}



// Load dependencies.
require __DIR__ . '/vendor/autoload.php';
$listPaperSizes = [];
foreach ( \Dompdf\Adapter\CPDF::$PAPER_SIZES as $paperSize => $paperParams )
{
	if ( ! preg_match( '/^[a-z]/', $paperSize ) || preg_match( '/^[a-c][0189]0?$/', $paperSize ) ||
	     preg_match( '/ra[0-5]$/', $paperSize ) )
	{
		continue;
	}
	if ( preg_match( '/^[a-c][0-9]$/', $paperSize ) )
	{
		$listPaperSizes[ $paperSize ] = strtoupper( $paperSize );
	}
	else
	{
		$listPaperSizes[ $paperSize ] = ucfirst( $paperSize );
	}
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
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>Source Report</td>
   <td>
    <select name="source">
     <option value=""></option>
<?php
foreach ( $listDataSources as $sourceID => $sourceLabel )
{
	echo '     <option value="', $module->escapeHTML( $sourceID ), '"',
	     ( ( $reportData['source'] ?? '' ) == $sourceID ? ' selected' : '' ),
	     '>', $module->escapeHTML( $sourceLabel ), '</option>', "\n";
}
?>
    </select>
   </td>
  </tr>
  <tr>
   <td>Paper Size</td>
   <td>
    <select name="pdf_size">
<?php
foreach ( $listPaperSizes as $paperSizeID => $paperSize )
{
	echo '     <option value="', $module->escapeHTML( $paperSizeID ), '"',
	     ( ( $reportData['pdf_size'] ?? 'a4' ) == $paperSizeID ? ' selected' : '' ),
	     '>', $module->escapeHTML( $paperSize ), '</option>', "\n";
}
?>
    </select>
    <select name="pdf_orientation">
<?php
foreach ( [ 'portrait', 'landscape' ] as $paperOrientation )
{
	echo '     <option value="', $module->escapeHTML( $paperOrientation ), '"',
	     ( ( $reportData['pdf_orientation'] ?? '' ) == $paperOrientation ? ' selected' : '' ),
	     '>', $module->escapeHTML( ucfirst( $paperOrientation ) ), '</option>', "\n";
}
?>
    </select>
   </td>
  </tr>
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
