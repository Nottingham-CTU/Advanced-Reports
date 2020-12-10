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
	$validQuery = ( mysqli_query( $conn, str_replace( '$$PROJECT$$', $module->getProjectId(),
	                                                  $_POST['sql_query'] ) ) !== false );
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_SQLCHK'] ) )
	{
		header( 'Content-Type: application/json' );
		if ( $validQuery )
		{
			echo 'true';
		}
		else
		{
			echo json_encode( mysqli_error( $conn ) );
		}
		exit;
	}
	if ( ! $validQuery )
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
<form method="post" id="sqlform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig ); ?>
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>SQL Query</td>
   <td>
    <textarea name="sql_query" spellcheck="false"
              style="height:500px;max-width:95%;font-family:monospace;white-space:pre"><?php
echo $reportData['sql_query'] ?? ''; ?></textarea>
    <br>
    <span class="field-desc">
     To use the current project ID, enter: <tt>$$PROJECT$$</tt>
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
<script type="text/javascript">
 (function ()
 {
   $('[name="sql_query"]')[0].onkeyup = function()
   {
     if ( this.value.toLowerCase().substring( 0, 7 ).replace( /[\r\n]/, ' ' ) != 'select ' )
     {
       this.setCustomValidity( 'Invalid SQL query: Must start with SELECT' )
     }
     else
     {
       this.setCustomValidity( '' )
     }
   }
   var vValidated = false
   $('#sqlform')[0].onsubmit = function()
   {
     if ( vValidated )
     {
       return true
     }
     $.ajax( { url : '<?php echo $module->getUrl( 'sql_edit.php?report_id=' . $reportID ); ?>',
               method : 'POST',
               data : { sql_query : $('[name=sql_query')[0].value },
                        headers : { 'X-RC-AdvRep-SQLChk' : '1' },
                        dataType : 'json',
                        success : function ( result )
                        {
                          if ( result === true )
                          {
                            vValidated = true
                            $('#sqlform')[0].submit()
                          }
                          else
                          {
                            var vMsg = 'Invalid SQL query: ' + result
                            $('[name="sql_query"]')[0].setCustomValidity( vMsg )
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
