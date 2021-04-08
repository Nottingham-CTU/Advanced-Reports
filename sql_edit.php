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
	$validQuery = ( mysqli_query( $conn, $module->sqlPlaceholderReplace( $_POST['sql_query'],
	                                                                     true ) ) !== false );
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
	$module->submitReportConfig( $reportID, true, 'image' );
	$reportData = [ 'sql_query' => $_POST['sql_query'], 'sql_type' => $_POST['sql_type'] ];
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
<?php $module->outputReportConfigOptions( $reportConfig, true, 'image' ); ?>
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>SQL Query</td>
   <td>
    <textarea name="sql_query" spellcheck="false"
              style="height:500px;max-width:95%;font-family:monospace;white-space:pre"><?php
echo $reportData['sql_query'] ?? ''; ?></textarea>
    <br>
    <span class="field-desc">
     You can use the following placeholder values in SQL queries:<br>
     <tt>$$DAG$$</tt> &#8212; the REDCap unique DAG ID of the user viewing the report
     (<i>NULL</i> if the user is not in a DAG)<br>
     <tt>$$PROJECT$$</tt> &#8212; the current project ID<br>
     <tt>$$QINT:<i>name</i>$$</tt> &#8212; the value of the named query string parameter, as an
     integer (<i>NULL</i> if the value is missing or not an integer)<br>
     <tt>$$QSTR:<i>name</i>$$</tt> &#8212; the value of the named query string parameter, as a
     string (<i>NULL</i> if the value is missing)<br>
     <tt>$$ROLE$$</tt> &#8212; the REDCap unique role ID of the user viewing the report
     (<i>NULL</i> if the user is not in a role)<br>
     <tt>$$USER$$</tt> &#8212; the username of the user viewing the report<br>&nbsp;
    </span>
   </td>
  </tr>
  <tr>
   <td>Result Type</td>
   <td>
    <label>
     <input type="radio" name="sql_type" value="normal" required<?php
		echo ( $reportData['sql_type'] ?? 'normal' ) == 'normal'
					? ' checked' : ''; ?>> Normal dataset
    </label>
    <br>
    <label>
     <input type="radio" name="sql_type" value="eav" required<?php
		echo ( $reportData['sql_type'] ?? 'normal' ) == 'eav'
					? ' checked' : ''; ?>> EAV dataset, omit row ID
    </label>
    <br>
    <label>
     <input type="radio" name="sql_type" value="eav-id" required<?php
		echo ( $reportData['sql_type'] ?? 'normal' ) == 'eav-id'
					? ' checked' : ''; ?>> EAV dataset, include row ID
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
