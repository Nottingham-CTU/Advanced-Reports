<?php
/**
 *	Gantt chart edit page.
 */

namespace Nottingham\AdvancedReports;



// Check user can edit Gantt reports and verify the report exists and is a Gantt report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'gantt' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'gantt' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );



// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// Validate and process data
	// - Check the groups of variables are complete.
	$hasLabelEvents = ( isset( $_POST['label_event'] ) && count( $_POST['label_event'] ) != 0 );
	$hasCategoryEvents = ( isset( $_POST['chart_start_event'] ) &&
	                       isset( $_POST['chart_end_event'] ) &&
	                       count( $_POST['chart_start_event'] ) +
	                       count( $_POST['chart_end_event'] ) != 0 );
	if ( $hasLabelEvents != $hasCategoryEvents ||
	     count( $_POST['label_name'] ) != count( $_POST['label_field'] ) ||
	     ( $hasLabelEvents && count( $_POST['label_name'] ) != count( $_POST['label_event'] ) ) ||
	     count( $_POST['chart_category'] ) != count( $_POST['chart_start_field'] ) ||
	     count( $_POST['chart_category'] ) != count( $_POST['chart_end_field'] ) ||
	     ( $hasCategoryEvents &&
	       ( count( $_POST['chart_category'] ) != count( $_POST['chart_start_event'] ) ||
	         count( $_POST['chart_category'] ) != count( $_POST['chart_end_event'] ) ) ) )
	{
		exit;
	}
	// - Build the dataset to save, discard empty groups, fail on invalid groups
	//   (invalid data should have been prevented by the client side validation,
	//    this is just an additional check)
	$listLabels = [];
	$listCategories = [];
	for ( $i = 0; $i < count( $_POST['label_name'] ); $i++ )
	{
		$infoLabel = [ 'name' => $_POST['label_name'][$i] ];
		if ( isset( $_POST['label_event'][$i] ) )
		{
			$infoLabel['event'] = $_POST['label_event'][$i];
		}
		$infoLabel['field'] = $_POST['label_field'][$i];
		if ( implode( '', $infoLabel ) == '' )
		{
			continue;
		}
		if ( in_array( '', $infoLabel, true ) )
		{
			exit;
		}
		$listLabels[] = $infoLabel;
	}
	for ( $i = 0; $i < count( $_POST['chart_category'] ); $i++ )
	{
		$infoCategory = [ 'name' => $_POST['chart_category'][$i] ];
		if ( isset( $_POST['chart_start_event'][$i] ) )
		{
			$infoCategory['start_event'] = $_POST['chart_start_event'][$i];
		}
		$infoCategory['start_field'] = $_POST['chart_start_field'][$i];
		if ( isset( $_POST['chart_end_event'][$i] ) )
		{
			$infoCategory['end_event'] = $_POST['chart_end_event'][$i];
		}
		$infoCategory['end_field'] = $_POST['chart_end_field'][$i];
		if ( implode( '', $infoCategory ) == '' )
		{
			continue;
		}
		if ( in_array( '', $infoCategory, true ) )
		{
			exit;
		}
		$listCategories[] = $infoCategory;
	}
	if ( count( $listLabels ) == 0 || count( $listCategories ) == 0 )
	{
		exit;
	}

	// Save data
	$module->submitReportConfig( $reportID, false );
	$reportData = [ 'labels' => $listLabels, 'chart_categories' => $listCategories ];
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}



// Output the report definition fields -- label fields.
function outputGanttLabelFields( $name = '', $event = '', $field = '' )
{
	global $module;
?>
  <tr>
   <td>Label Name</td>
   <td>
    <input type="text" name="label_name[]" value="<?php echo $module->escapeHTML( $name ); ?>">
   </td>
  </tr>
  <tr>
   <td>Label Field</td>
   <td>
<?php
if ( \REDCap::isLongitudinal() )
{
	$module->outputEventDropdown( 'label_event[]', $event );
	echo ' ';
}
$module->outputFieldDropdown( 'label_field[]', $field );
echo "\n";
?>
   </td>
  </tr>
<?php
}



// Output the report definition fields -- chart category fields.
function outputGanttChartFields( $name = '', $startEvent = '', $startField = '',
                                 $endEvent = '', $endField = '' )
{
	global $module;
?>
  <tr>
   <td>Name</td>
   <td>
    <input type="text" name="chart_category[]" value="<?php echo $module->escapeHTML( $name ); ?>">
   </td>
  </tr>
  <tr>
   <td>Start Date Field</td>
   <td>
<?php
if ( \REDCap::isLongitudinal() )
{
	$module->outputEventDropdown( 'chart_start_event[]', $startEvent );
	echo ' ';
}
$module->outputFieldDropdown( 'chart_start_field[]', $startField, 'date,calc' );
echo "\n";
?>
   </td>
  </tr>
  <tr>
   <td>End Date Field</td>
   <td>
<?php
if ( \REDCap::isLongitudinal() )
{
	$module->outputEventDropdown( 'chart_end_event[]', $endEvent );
	echo ' ';
}
$module->outputFieldDropdown( 'chart_end_field[]', $endField, 'date,calc' );
echo "\n";
?>
   </td>
  </tr>
<?php
}



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();
?>
<div class="projhdr">
 Advanced Reports &#8212;
 Edit Gantt Report: <?php echo $module->escapeHTML( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' );
?>"><i class="fas fa-arrow-circle-left fs11"></i> Back to edit reports</a>
</p>
<form method="post" id="advrep-gantt-form">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, false ); ?>
  <tr><th colspan="2">Report Definition - Label Fields</th></tr>
<?php

if ( $reportData !== null )
{
	foreach ( $reportData['labels'] as $infoLabel )
	{
		outputGanttLabelFields( $infoLabel['name'],
		                        $infoLabel['event'] ?? '', $infoLabel['field'] );
	}
}
outputGanttLabelFields();

?>
  <tr>
   <td></td>
   <td>
    <a href="#" id="gantt-add-lbl"><i class="fas fa-plus-circle fs12"></i> Add label field</a>
   </td>
  </tr>
  <tr><th colspan="2">Report Definition - Chart Categories</th></tr>
<?php

if ( $reportData !== null )
{
	foreach ( $reportData['chart_categories'] as $infoCategory )
	{
		outputGanttChartFields( $infoCategory['name'],
		                        $infoCategory['start_event'] ?? '', $infoCategory['start_field'],
		                        $infoCategory['end_event'] ?? '', $infoCategory['end_field'] );
	}
}
outputGanttChartFields();

?>
  <tr>
   <td></td>
   <td>
    <a href="#" id="gantt-add-cat"><i class="fas fa-plus-circle fs12"></i> Add chart category</a>
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
 $(function ()
   {
     var vFuncLbl = function()
     {
       var vLblStatus = { name : [], event : [], field : [] }
       var vHasLbl = { name : false, event : false, field : false }
       $('[name="label_name[]"], [name="label_event[]"], [name="label_field[]"]').each(
         function()
         {
           var vFieldType = this.name.substring( 6, 11 ).replace( '[', '' )
           vLblStatus[ vFieldType ].push( this.value != '' )
           if ( this.value != '' )
           {
             vHasLbl[ vFieldType ] = true
           }
         }
       )
       var vLongitudinal = ( vLblStatus.event.length > 0 )
       $('[name="label_name[]"], [name="label_event[]"], [name="label_field[]"]').each(
         function( vIndex )
         {
           var vGroup = Math.floor( vIndex / ( vLongitudinal ? 3 : 2 ) )
           var vFieldType = this.name.substring( 6, 11 ).replace( '[', '' )
           var vNoGroups = ( ! vHasLbl.name && ! vHasLbl.event && ! vHasLbl.field )
           var vFieldValid = ( vLblStatus[ vFieldType ][ vGroup ] ||
                               ( ! vLblStatus.name[ vGroup ] && ! vLblStatus.field[ vGroup ] &&
                                 ( ! vLongitudinal || ! vLblStatus.event[ vGroup ] ) ) )
           if ( vNoGroups || ! vFieldValid )
           {
             this.setCustomValidity( 'Value expected' )
           }
           else
           {
             this.setCustomValidity( '' )
           }
         }
       )
     }
     vFuncLbl()
     $('[name="label_name[]"]').on( 'keyup paste', vFuncLbl )
     $('[name="label_event[]"], [name="label_field[]"]').on( 'change', vFuncLbl )
     var vFuncCat = function()
     {
       var vCatStatus = { name : [], sevent : [], sfield : [], eevent : [], efield : [] }
       var vHasCat = { name : false, sevent : false, sfield : false, eevent : false, efield : false }
       $('[name="chart_category[]"], [name="chart_start_event[]"], [name="chart_start_field[]"],' +
         ' [name="chart_end_event[]"], [name="chart_end_field[]"]').each( function()
       {
         var vFieldType = ''
         if ( this.name.substring( 6, 7 ) == 's' )
         {
           vFieldType = 's' + this.name.substring( 12, 17 )
         }
         else if ( this.name.substring( 6, 7 ) == 'e' )
         {
           vFieldType = 'e' + this.name.substring( 10, 15 )
         }
         else
         {
           vFieldType = 'name'
         }
         vCatStatus[ vFieldType ].push( this.value != '' )
         if ( this.value != '' )
         {
           vHasCat[ vFieldType ] = true
         }
       })
       var vLongitudinal = ( vCatStatus.sevent.length > 0 )
       $('[name="chart_category[]"], [name="chart_start_event[]"], [name="chart_start_field[]"],' +
         ' [name="chart_end_event[]"], [name="chart_end_field[]"]').each( function( vIndex )
       {
         var vGroup = Math.floor( vIndex / ( vLongitudinal ? 5 : 3 ) )
         var vFieldType = ''
         if ( this.name.substring( 6, 7 ) == 's' )
         {
           vFieldType = 's' + this.name.substring( 12, 17 )
         }
         else if ( this.name.substring( 6, 7 ) == 'e' )
         {
           vFieldType = 'e' + this.name.substring( 10, 15 )
         }
         else
         {
           vFieldType = 'name'
         }
         var vNoGroups = ( ! vHasCat.name && ! vHasCat.sevent && ! vHasCat.sfield &&
                           ! vHasCat.eevent && ! vHasCat.efield )
         var vFieldValid = ( vCatStatus[ vFieldType ][ vGroup ] ||
                             ( ! vCatStatus.name[ vGroup ] &&
                               ! vCatStatus.sfield[ vGroup ] && ! vCatStatus.efield[ vGroup ] &&
                               ( ! vLongitudinal || ( ! vCatStatus.sevent[ vGroup ] &&
                                                      ! vCatStatus.eevent[ vGroup ] ) ) ) )
         if ( vNoGroups || ! vFieldValid )
         {
           this.setCustomValidity( 'Value expected' )
         }
         else
         {
           this.setCustomValidity( '' )
         }
       })
     }
     vFuncCat()
     $('[name="chart_category[]"]').on( 'keyup paste', vFuncCat )
     $('[name="chart_start_event[]"], [name="chart_start_field[]"]').on( 'change', vFuncCat )
     $('[name="chart_end_event[]"], [name="chart_end_field[]"]').on( 'change', vFuncCat )
     $('#gantt-add-lbl').click( function()
     {
       var vNew = $('<tr><td colspan="2"></td></tr><?php
ob_start();
outputGanttLabelFields();
echo str_replace( "\n", '\n', addslashes( ob_get_clean() ) );
?>')
       vNew.find('[name="label_name[]"]').on( 'keyup paste', vFuncLbl )
       vNew.find('select').on( 'change', vFuncLbl )
       vNew.insertBefore( $('#gantt-add-lbl').parent().parent() )
       return false
     })
     $('#gantt-add-cat').click( function()
     {
       var vNew = $('<tr><td colspan="2"></td></tr><?php
ob_start();
outputGanttChartFields();
echo str_replace( "\n", '\n', addslashes( ob_get_clean() ) );
?>')
       vNew.find('[name="chart_category[]"]').on( 'keyup paste', vFuncCat )
       vNew.find('select').on( 'change', vFuncCat )
       vNew.insertBefore( $('#gantt-add-cat').parent().parent() )
       return false
     })
   })
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
