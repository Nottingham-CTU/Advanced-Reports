<?php
/**
 *	Gantt chart view page.
 */

namespace Nottingham\AdvancedReports;



// Define constants for day/week length, and time spans used in chart.
define( 'GANTT_DAY', 86400 );
define( 'GANTT_WEEK', ( GANTT_DAY * 7 ) );
define( 'GANTT_MONTH', ( GANTT_DAY * 28 ) );
define( 'GANTT_SPLIT_DAY', 6 );
define( 'GANTT_SPLIT_WEEK', 7 );
define( 'GANTT_SPLIT_MONTH', 8 );
define( 'GANTT_STEP_DAY', ( GANTT_DAY / GANTT_SPLIT_DAY ) );
define( 'GANTT_STEP_WEEK', ( GANTT_WEEK / GANTT_SPLIT_WEEK ) );
define( 'GANTT_STEP_MONTH', ( GANTT_MONTH / GANTT_SPLIT_MONTH ) );

// Define mapping of Gantt zoom level names to numbers.
$zoomLevels = [ 1 => 'ultra', 2 => 'more', 3 => 'compact', 4 => 'standard', 5 => 'expand' ];



// Verify the report exists, is a Gantt report, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'gantt' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );



// Check user can view this report, redirect to main reports page if not.
if ( ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}



// Determine the selected zoom level.
if ( isset( $_GET['gantt_mode'] ) && in_array( $_GET['gantt_mode'], $zoomLevels ) )
{
	$module->query( "DO GET_LOCK('rc-mod-advrep-ganttzoom',20)", [] );
	$listUserZoomLevels = json_decode( $module->getSystemSetting('user-gantt-zoom') ?? '[]', true );
	if ( $_GET['gantt_mode'] == 'standard' )
	{
		unset( $listUserZoomLevels[USERID] );
	}
	else
	{
		$listUserZoomLevels[USERID] = $_GET['gantt_mode'];
	}
	$module->setSystemSetting( 'user-gantt-zoom', json_encode( $listUserZoomLevels ) );
	$module->query( "DO RELEASE_LOCK('rc-mod-advrep-ganttzoom')", [] );
	header( 'Location: ' . $module->makeQueryURL( 'gantt_mode' ) );
	exit;
}
else
{
	$listUserZoomLevels = json_decode( $module->getSystemSetting('user-gantt-zoom') ?? '[]', true );
	if ( isset( $listUserZoomLevels[USERID] ) )
	{
		$_GET['gantt_mode'] = $listUserZoomLevels[USERID];
	}
	else
	{
		$_GET['gantt_mode'] = 'standard';
	}
}



// Determine the report date range.
// Defaults to 'standard' mode, which splits 'compact' and 'expand' modes.
// Expand mode shows a scale of 1 day, compact mode shows a scale of 1 week.
$reportStart = strtotime( '-' . ( gmdate( 'N' ) - 1 ) . ' days 00:00:00 UTC' );
$reportMiddle = strtotime( '+2 weeks 00:00:00 UTC', $reportStart );
$reportEnd = strtotime( 'first day of +4 months 00:00:00 UTC', $reportStart );
// Squash mode provides for more compact (week/month) and ultra compact (month) scales.
$squashMode = ( in_array( $_GET['gantt_mode'], [ 'more', 'ultra' ] ) );

if ( $_GET['gantt_mode'] == 'compact' )
{
	$reportMiddle = $reportStart;
	$reportEnd = strtotime( 'first day of +6 months 00:00:00 UTC', $reportStart );
}
elseif ( $_GET['gantt_mode'] == 'expand' )
{
	$reportEnd = strtotime( '+4 weeks 00:00:00 UTC', $reportStart );
	$reportMiddle = $reportEnd;
}
elseif ( $_GET['gantt_mode'] == 'more' )
{
	$reportMiddle = strtotime( 'first day of +3 months 00:00:00 UTC', $reportStart );
	$reportEnd = strtotime( 'first day of +13 months 00:00:00 UTC', $reportStart );
}
elseif ( $_GET['gantt_mode'] == 'ultra' )
{
	$reportMiddle = $reportStart;
	$reportEnd = strtotime( 'first day of +21 months 00:00:00 UTC', $reportStart );
}

// If a valid report start and/or end date is specified, use instead of the default for the mode.
if ( isset( $_GET['gantt_start'] ) &&
     preg_match( '/^[0-9]{4}-(02-(0[1-9]|[12][0-9])|(0[469]|11)-(0[1-9]|[12][0-9]|30)|' .
                 '(0[13578]|1[02])-(0[1-9]|[12][0-9]|3[01]))$/', $_GET['gantt_start'] ) )
{
	$reportStart = strtotime( $_GET['gantt_start'] . ' 00:00:00 UTC' );
	if ( $reportStart > $reportMiddle || in_array( $_GET['gantt_mode'], [ 'compact', 'ultra' ] ) )
	{
		$reportMiddle = $reportStart;
	}
}
if ( isset( $_GET['gantt_end'] ) &&
     preg_match( '/^[0-9]{4}-(02-(0[1-9]|[12][0-9])|(0[469]|11)-(0[1-9]|[12][0-9]|30)|' .
                 '(0[13578]|1[02])-(0[1-9]|[12][0-9]|3[01]))$/', $_GET['gantt_end'] ) )
{
	$reportEnd = strtotime( $_GET['gantt_end'] . ' 00:00:00 UTC' );
	if ( $reportEnd < $reportMiddle || $_GET['gantt_mode'] == 'expand' )
	{
		$reportMiddle = $reportEnd;
	}
}



// Get the project data.
// The REDCap::getData() function is used here with the JSON return format (which is decoded into
// a PHP array), instead of the PHP array return format. This is because the JSON return format
// provides better support for SQL field choice labels. It is also arguably more straighforward to
// implement support for repeating events/instruments this way.
$listProjectData = [];
foreach ( [ 'values' => false, 'labels' => true ] as $dataMode => $dataIsLabels )
{
	$listProjectData[ $dataMode ] =
		json_decode( \REDCap::getData( [ 'return_format' => 'json',
		                                 'combine_checkbox_values' => true,
		                                 'exportAsLabels' => $dataIsLabels ] ), true );
}



// Parse the project data into a format ready for presenting as a Gantt chart.
$listChartEntries = [];
$latestItemEndDate = $reportStart;
foreach ( $listProjectData['values'] as $rowNum => $infoDataValues )
{
	$infoDataLabels = $listProjectData['labels'][$rowNum];
	$recordID = $infoDataValues[ \REDCap::getRecordIdField() ];
	if ( ! isset( $listChartEntries[ $recordID ] ) )
	{
		$listChartEntries[ $recordID ] = [ 'labels' => [], 'categories' => [],
		                                   'categories_s' => [], 'categories_e' => [] ];
	}
	// Get the values for the chart labels (displayed on the left of the chart).
	foreach ( $reportData['labels'] as $infoLabel )
	{
		if ( \REDCap::isLongitudinal() &&
		     $infoDataValues['redcap_event_name'] != $infoLabel['event'] )
		{
			continue;
		}

		if ( $infoDataLabels[ $infoLabel['field'] ] != '' &&
		     ! isset( $listChartEntries[ $recordID ]['labels'][ $infoLabel['name'] ] ) )
		{
			$value = str_replace( ',', ', ', $infoDataLabels[ $infoLabel['field'] ] );
			$listChartEntries[ $recordID ]['labels'][ $infoLabel['name'] ] = $value;

		}
	}
	// Get the names and date ranges of the chart items. The dates are captured separately (in case
	// they are on different events) and merged later.
	foreach ( $reportData['chart_categories'] as $infoCategory )
	{
		if ( \REDCap::isLongitudinal() &&
		     $infoDataValues['redcap_event_name'] != $infoCategory['start_event'] &&
		     $infoDataValues['redcap_event_name'] != $infoCategory['end_event'] )
		{
			continue;
		}
		// If a chart item can be constructed multiple times from different instances of repeating
		// events/instruments, it will appear multiple times on the chart (if within date ranges).
		$repeatInstance = ( isset( $infoDataValues['redcap_repeat_instance'] ) &&
		                    $infoDataValues['redcap_repeat_instance'] != '' )
		                  ? $infoDataValues['redcap_repeat_instance'] : 1;
		if ( ! \REDCap::isLongitudinal() ||
		     $infoDataValues['redcap_event_name'] == $infoCategory['start_event'] )
		{
			$date = $infoDataValues[ $infoCategory['start_field'] ];
			if ( $date != '' )
			{
				if ( ! preg_match( '/^-?[0-9]+$/', $date ) )
				{
					$date = strtotime( $date . ' UTC' );
				}
				if ( $date < $reportEnd )
				{
					$listChartEntries[ $recordID ]['categories_s'][
					                             $infoCategory['name'] ][ $repeatInstance ] = $date;
				}
			}
		}
		if ( ! \REDCap::isLongitudinal() ||
		     $infoDataValues['redcap_event_name'] == $infoCategory['end_event'] )
		{
			$date = $infoDataValues[ $infoCategory['end_field'] ];
			if ( $date != '' )
			{
				if ( ! preg_match( '/^-?[0-9]+$/', $date ) )
				{
					$date = strtotime( $date . ' UTC' );
				}
				if ( $date > $reportStart )
				{
					$listChartEntries[ $recordID ]['categories_e'][
					                             $infoCategory['name'] ][ $repeatInstance ] = $date;
					if ( $latestItemEndDate < $date )
					{
						$latestItemEndDate = $date;
					}
				}
			}
		}
	} // end foreach $reportData['chart_categories']
} // end foreach $listProjectData



// Check each chart entry to make sure it has at least one label and at least one item. Any that
// don't will be discarded. Chart item dates are also joined up here.
foreach ( $listChartEntries as $recordID => $infoChartEntry )
{
	if ( empty( $infoChartEntry['labels'] ) )
	{
		unset( $listChartEntries[ $recordID ] );
		continue;
	}
	foreach ( $infoChartEntry['categories_s'] as $categoryName => $infoCategoryStart )
	{
		foreach ( $infoCategoryStart as $repeatInstance => $startDate )
		{
			if ( isset( $infoChartEntry['categories_e'][ $categoryName ][ $repeatInstance ] ) )
			{
				$endDate = $infoChartEntry['categories_e'][ $categoryName ][ $repeatInstance ];
				if ( $startDate < $endDate )
				{
					$listChartEntries[ $recordID ]['categories'][] = [ 'name' => $categoryName,
						                                               'start' => $startDate,
						                                               'end' => $endDate ];
				}
			}
		}
	}
	if ( empty( $listChartEntries[ $recordID ]['categories'] ) )
	{
		unset( $listChartEntries[ $recordID ] );
		continue;
	}
	unset( $listChartEntries[ $recordID ]['categories_s'] );
	unset( $listChartEntries[ $recordID ]['categories_e'] );
}



// Get the full list of categories.
// This is used for styling the chart items and displaying the legend.
$listCategories = [];
foreach ( $reportData['chart_categories'] as $infoCategory )
{
	$listCategories[] = $infoCategory['name'];
}



// Display the project header and report navigation links.
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->outputViewReportHeader( $reportConfig['label'], 'gantt' );


?>
<p>
 <b>Zoom:</b>
 &nbsp;&nbsp;
 &#8211;&nbsp;&nbsp;&nbsp;<span style="width:250px;display:inline-block"
                                id="ganttzoom"></span>&nbsp;&nbsp;&nbsp;+
</p>
<?php

if ( $latestItemEndDate >= $reportEnd )
{

?>
<p>
 <b>Data range:</b>
 <?php echo $module->makeQueryLink( 'standard', 'gantt_end' ), "\n"; ?>
 |
 <?php echo $module->makeQueryLink( 'show all forthcoming', 'gantt_end',
                                    gmdate( 'Y-m-d', $latestItemEndDate ) ), "\n"; ?>
</p>
<?php

}

?>
<div class="mod-advrep-gantt" style="grid-template-columns:repeat(<?php
echo count( $reportData['labels'] ) ?>,min-content)">
<?php


foreach ( $reportData['labels'] as $infoLabel )
{

?>
 <div class="mod-advrep-gantt-hdr"><?php echo $module->escapeHTML( $infoLabel['name'] ); ?></div>
<?php

}
$prevYearHdr = '';
$prevMonthHdr = '';
$prevDateIncrement = '';
if ( $squashMode )
{
	$dateIncrement = GANTT_WEEK;
	$dateSpan = GANTT_SPLIT_WEEK;
	$dateStyle = ';border-bottom-width:5px';
}
else
{
	$dateIncrement = GANTT_DAY;
	$dateSpan = GANTT_SPLIT_DAY;
	$dateStyle = '';
}
for ( $date = $reportStart; $date < $reportEnd; $date += $dateIncrement )
{
	if ( $date >= $reportMiddle )
	{
		if ( $squashMode )
		{
			$dateIncrement = GANTT_MONTH;
			$dateSpan = GANTT_SPLIT_MONTH;
			$dateStyle = ';border-bottom-width:8px';
		}
		else
		{
			$dateIncrement = GANTT_WEEK;
			$dateSpan = GANTT_SPLIT_WEEK;
			$dateStyle = ';border-bottom-width:5px';
		}
	}
	$thisYearHdr = gmdate( 'Y', $date );
	if ( $thisYearHdr == $prevYearHdr )
	{
		$thisYearHdr = '&nbsp;';
	}
	else
	{
		$prevYearHdr = $thisYearHdr;
	}
	$thisMonthHdr = gmdate( 'M', $date );
	if ( $thisMonthHdr == $prevMonthHdr && $dateIncrement == $prevDateIncrement )
	{
		$thisMonthHdr = '&nbsp;&nbsp;&nbsp;';
	}
	else
	{
		$prevMonthHdr = $thisMonthHdr;
		$prevDateIncrement = $dateIncrement;
	}
	$thisDayHdr = gmdate( 'j', $date );
	$thisDateHdr = '<div>' . $thisYearHdr . '</div><span>' .
	               $thisMonthHdr . '&nbsp;</span>' . $thisDayHdr;
?>
 <div class="mod-advrep-gantt-date" style="grid-column:span <?php
	echo $dateSpan, $dateStyle; ?>"><?php echo $thisDateHdr; ?></div>
<?php
}
?>
<?php
foreach ( $listChartEntries as $infoChartEntry )
{
	$firstLabel = true;
	foreach ( $reportData['labels'] as $infoLabel )
	{
		$labelValue = $infoChartEntry['labels'][$infoLabel['name']] ?? '';
		$chartEntryLines = 2;
?>
 <div style="grid-row-end:span <?php
		echo count( $infoChartEntry['categories'] ), $firstLabel ? ';grid-column-start:1' : '';
?>"><?php echo $labelValue == '' ? '&nbsp;' : $module->escapeHTML( $labelValue ); ?></div>
<?php
		$firstLabel = false;
	}
	foreach ( $infoChartEntry['categories'] as $infoChartCategory )
	{
		// Determine the category index (for applying styles).
		$categoryIndex = array_search( $infoChartCategory['name'], $listCategories );
		// Determine the start/end timestamps from the category definition and record data.
		$itemStartTime = $infoChartCategory['start'];
		$itemEndTime = $infoChartCategory['end'];
		// Fit the record/category item within the time range of the chart.
		$itemStartTime = $itemStartTime < $reportStart ? $reportStart : $itemStartTime;
		$itemEndTime = $itemEndTime > $reportEnd ? $reportEnd : $itemEndTime;
		// Place the item correctly within the chart.
		$itemStart = 1 + count( $reportData['labels'] );
		$itemStep1 = $squashMode ? GANTT_STEP_WEEK : GANTT_STEP_DAY;
		$itemStep2 = $squashMode ? GANTT_STEP_MONTH : GANTT_STEP_WEEK;
		if ( $itemStartTime > $reportMiddle )
		{
			$itemStart += floor( ( $reportMiddle - $reportStart ) / $itemStep1 );
			$itemStart += floor( ( $itemStartTime - $reportMiddle ) / $itemStep2 );
		}
		else
		{
			$itemStart += floor( ( $itemStartTime - $reportStart ) / $itemStep1 );
		}
		$itemEnd = 1 + count( $reportData['labels'] );
		if ( $itemEndTime > $reportMiddle )
		{
			$itemEnd += floor( ( $reportMiddle - $reportStart ) / $itemStep1 );
			$itemEnd += floor( ( $itemEndTime - $reportMiddle ) / $itemStep2 );
		}
		else
		{
			$itemEnd += ceil( ( $itemEndTime - $reportStart ) / $itemStep1 );
		}
?>
 <div class="mod-advrep-chart-style<?php echo $categoryIndex; ?>" style="grid-column:<?php
echo "$itemStart/$itemEnd"; ?>" title="<?php
		echo $module->escapeHTML( $infoChartCategory['name'] ), "\n";
		if ( gmdate( 'H:i ', $infoChartCategory['start'] ) .
		     gmdate( 'H:i', $infoChartCategory['end'] ) == '00:00 00:00' )
		{
			$tooltipDateFormat = 'd M Y';
		}
		else
		{
			$tooltipDateFormat = 'd M Y  H:i';
		}
		echo 'Start: ', gmdate( $tooltipDateFormat, $infoChartCategory['start'] ), "\n";
		echo 'End:  ', gmdate( $tooltipDateFormat, $infoChartCategory['end'] );
?>"><?php echo $module->escapeHTML( $infoChartCategory['name'] ); ?></div>
<?php
	}
}
?>
</div>
<p>&nbsp;</p>
<div class="mod-advrep-gantt-key">
<?php
foreach ( $listCategories as $categoryIndex => $categoryName )
{
?>
 <div><div class="mod-advrep-chart-style<?php
	echo $categoryIndex; ?>"></div><?php echo $module->escapeHTML( $categoryName ); ?></div>
<?php
}
?>
</div>
<script type="text/javascript">
  $(function()
  {
    var vZoomLevels = <?php echo json_encode( $zoomLevels ), "\n"; ?>
    $('#ganttzoom').slider({min:1,max:5,value:<?php
echo array_search( $_GET['gantt_mode'], $zoomLevels );
?>,change:function(e,u){window.location+='&gantt_mode='+vZoomLevels[u.value]}})
  })
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

