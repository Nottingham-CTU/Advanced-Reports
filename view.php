<?php
/**
 *	View report page. Displays loading screen until the report is ready.
 */

namespace Nottingham\AdvancedReports;


// Verify the report exists.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Get the report type and URL to the specific view page.
$reportType = $listReports[$reportID]['type'];
$reportLabel = $listReports[$reportID]['label'];
$reportURL = preg_replace( '!^(https?://[^/]+).*!', '$1', $module->getUrl( 'view.php' ) );
$reportURL .= preg_replace( '/([?&])page=view/', '$1page=' . $reportType . '_view',
                            $_SERVER['REQUEST_URI'] );
if ( $reportType == 'pdf' || $reportType == 'gantt' )
{
	header( 'Location: ' . $reportURL );
	exit;
}


// Display the header.
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

?>
<div id="advanced-report-content">
 <div class="projhdr"><?php echo $module->escapeHTML( $reportLabel ); ?></div>
 <p><a style="font-size:1.3em"
       href="<?php echo $module->escapeHTML( $reportURL ); ?>">Continue to report...</a></p>
</div>
<script type="text/javascript">
  (function()
  {
    $('#advanced-report-content p').html('Report loading...<br>' +
                                         '<progress value="0" style="width:60%"></progress>')
    var vLoadingInterval = setInterval(function(){
      var vVal = $('#advanced-report-content progress').val() - 0
      vVal = vVal + ( ( 0.95 - vVal ) * 0.05 )
      $('#advanced-report-content progress').val( vVal )
    }, 500)
    $.ajax( <?php echo $module->escapeJSString( $reportURL ); ?>,
           { headers: {'Advanced-Reports-AJAX':'1'},
             success: function( vData )
                      {
                        clearInterval( vLoadingInterval )
                        $('#advanced-report-content').html( vData )
                      },
             error: function( vData )
                    {
                      clearInterval( vLoadingInterval )
                      $('#advanced-report-content').text( vData.responseText )
                    } } )
  })()
</script>
<?php

// Display the footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
