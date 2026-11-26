<?php
/**
 *	Word Document Reports edit page.
 */

namespace Nottingham\AdvancedReports;


// Check user can edit Word Document reports and verify the report exists and is a Word Document report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'docx' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'docx' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );
$canSaveIfPublic = ( ! $module->getSystemSetting( 'admin-only-public' ) ||
                     $module->getUser()->isSuperUser() );
$allowSQLSource = $module->getSystemSetting( 'allow-sql-source' );


// Get the reports which can be used as a data source for this report.
$listDataSources = [];
foreach ( $listReports as $rID => $rConfig )
{
	if ( $rConfig['type'] == 'instrument' ||
	     ( $allowSQLSource && in_array( $rConfig['type'], [ 'sql', 'system' ] ) ) )
	{
		$listDataSources[ $rID ] = $rConfig['label'];
	}
}


// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// Validate data
	$validationMsg = '';
	if ( ! $canSaveIfPublic && $_POST['report_as_public'] == 'Y' )
	{
		$validationMsg = 'Reports with Public access can only be saved by an administrator.';
	}
	// - Check the data source is specified and is valid.
	if ( $validationMsg == '' )
	{
		if ( ! isset( $listDataSources[ $_POST['source'] ] ) )
		{
			$validationMsg = 'A data source has not been selected.';
		}
	}
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_DOCXQUERYCHK'] ) )
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
	$module->submitReportConfig( $reportID, false, [ 'saveable', 'public' ] );
	$_POST['docx'] = str_replace( "\r\n", "\n", $_POST['docx'] );
	$reportData = [ 'source' => $_POST['source'], 'docx' => $_POST['docx'] ];
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}
// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit Word Document Report: <?php
echo $module->escapeHTML( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' );
?>" class=""><i class="fas fa-arrow-left fs11"></i> Back to edit reports</a>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php echo $module->getUrl( 'README-DOCX.md' );
?>" target="_blank"><i class="fas fa-book fs11"></i> View Documentation</a>
</p>
<form method="post" id="queryform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, false, [ 'saveable', 'public' ] ); ?>
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
    <td>HTML Template</td>
    <td>
     <textarea name="docx" style="height:600px;max-width:95%;white-space:pre"><?php
echo $reportData['docx'] ?? ''; ?></textarea>
     <br>
     <span class="field-desc">
      The Word document will be rendered based on this HTML template.<br>
      To insert an image, copy an image file, then paste into the HTML template (right click -> Paste).
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
     $.ajax( { url : window.location.href,
               method : 'POST',
               data : $('#queryform').serialize(),
                        headers : { 'X-RC-AdvRep-DOCXQueryChk' : '1' },
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
   $('[name="docx"]').on('paste', function(ev)
   {
     var vListFiles = ev.originalEvent.clipboardData.files
     if ( vListFiles.length < 1 )
     {
       return
     }
     ev.preventDefault()
     $(vListFiles).each(async function()
     {
       if ( this.type.substring(0,6) == 'image/' )
       {
         var vData = await this.bytes()
         vData = vData.toBase64()
         vData = '<img src="data:image/png;base64,' + vData + '">'
         var vPos = $('[name="docx"]')[0].selectionStart
         var vEnd = $('[name="docx"]')[0].selectionEnd
         var vVal = $('[name="docx"]').val()
         $('[name="docx"]').val( vVal.slice(0,vPos) + vData + vVal.slice(vEnd) )
         $('[name="docx"]')[0].selectionEnd = vPos + vData.length
       }
     })
   })
 })()
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';