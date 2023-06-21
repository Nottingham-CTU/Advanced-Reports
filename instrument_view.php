<?php
/**
 *	Project Query Reports view page.
 */

namespace Nottingham\AdvancedReports;



// Verify the report exists, is a project query, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'instrument' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Check user can view this report, redirect to main reports page if not.
if ( ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}

// Get the report data.
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );
$resultParams = [ 'return_format' => 'json-array', 'combine_checkbox_values' => true,
                  'exportDataAccessGroups' => true, 'exportSurveyFields' => true,
                  'returnBlankForGrayFormStatus' => true,
                  'removeMissingDataCodes' => $reportData['nomissingdatacodes'] ];
$redcapFields = [ 'redcap_event_name', 'redcap_repeat_instance', 'redcap_data_access_group' ];

// Build the result table.
$resultTable = [[]];
foreach ( $reportData['forms'] as $queryForm )
{
	$form = $queryForm['form'];
	$alias = $queryForm['alias'] == '' ? $form : $queryForm['alias'];
	$fields = array_unique( array_merge( [ \REDCap::getRecordIdField() ],
	                                     \REDCap::getFieldNames( $form ) ) );
	$formValues = \REDCap::getData( $resultParams +
	                                [ 'exportAsLabels' => false, 'fields' => $fields ] );
	$formLabels = \REDCap::getData( $resultParams +
	                                [ 'exportAsLabels' => true, 'fields' => $fields ] );
	$newResultTable = [];
	foreach ( $resultTable as $resultRow )
	{
		foreach ( $formValues as $i => $formValuesRow )
		{
			if ( $formValuesRow[ $form . '_complete' ] === '' )
			{
				continue;
			}
			$formLabelsRow = $formLabels[$i];
			if ( $queryForm['on'] == '' ) // TODO
			{
				$newResultRow = $resultRow;
				$insertedRedcapFields = false;
				foreach ( $fields as $field )
				{
					$newResultRow[ $alias . '.' . $field ] =
						[ 'value' => $formValuesRow[$field], 'label' => $formLabelsRow[$field] ];
					if ( ! $insertedRedcapFields )
					{
						foreach ( $redcapFields as $field )
						{
							if ( isset( $formValuesRow[$field] ) )
							{
								$newResultRow[ $alias . '.' . $field ] =
										[ 'value' => $formValuesRow[$field],
										  'label' => $formLabelsRow[$field] ];
							}
						}
						$insertedRedcapFields = true;
					}
				}
				$newResultTable[] = $newResultRow;
			}
		}
	}
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}
// Display the project header and report navigation links.

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->outputViewReportHeader( $reportConfig['label'], 'pquery', true );

// Initialise the row counter.
$rowCount = 0;


?>
<table id="mod-advrep-table" class="mod-advrep-datatable dataTable">
 <thead>
  <tr>
<?php
if ( count( $resultTable ) > 0 )
{
	foreach ( $resultTable[0] as $field => $value )
	{
?>
   <th class="sorting"><?php echo $module->escapeHTML( $field ); ?></th>
<?php
	}
}
?>
  </tr>
 </thead>
 <tbody>
<?php
foreach ( $resultTable as $resultRow )
{
	$rowCount++;
?>
  <tr>
<?php
	foreach ( $resultRow as $value )
	{
?>
   <td><?php echo $module->parseHTML( $value['label'] ); ?></td>
<?php
	}
	if ( $rowCount == 0 )
	{
?>
  <tr><td>No rows returned</td></tr>
<?php
	}
?>
  </tr>
<?php
}
?>
 </tbody>
</table>
<?php

if ( $rowCount > 0 )
{
?>
<p>Total rows returned: <span id="filtercount"></span><?php echo $rowCount; ?></p>
<?php
}

echo json_encode( $resultTable, JSON_PRETTY_PRINT );


$module->outputViewReportJS();


// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';