<?php
/**
 *	System Query Reports edit page.
 */

namespace Nottingham\AdvancedReports;



// Check user can edit System Query reports and verify the report exists and is an System Query
// report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'system' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'system' )
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
	// - Check the forms/fields are specified if an alias or join condition is specified.
	if ( $validationMsg == '' )
	{
		foreach ( $_POST['query_form'] as $i => $formName )
		{
			if ( ( $formName . $_POST['query_form_proj'][$i] . $_POST['query_form_alias'][$i] .
			       ( $i == 0 ? '' : $_POST['query_form_on'][ $i - 1 ] ) != '' ) &&
			     ( $formName == '' || $_POST['query_form_proj'][$i] == '' ||
			       $_POST['query_form_alias'][$i] == '' ) )
			{
				$validationMsg = 'For each table/instrument, the project, table/instrument and ' .
				                 'alias must be specified.';
				break;
			}
		}
	}
	if ( $validationMsg == '' )
	{
		foreach ( $_POST['query_select_field'] as $i => $fieldName )
		{
			if ( $fieldName == '' && $_POST['query_select_alias'][$i] != '' )
			{
				$validationMsg = 'Field cannot be empty if alias specified.';
			}
		}
	}
	// - Check the validity of forms join condition logic.
	if ( $validationMsg == '' )
	{
		try
		{
			foreach ( $_POST['query_form_on'] as $formCond )
			{
				if ( $formCond != '' )
				$module->parseLogic( $formCond, false, false, false );
			}
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in form join condition logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of condition logic.
	if ( $validationMsg == '' && $_POST['query_where'] != '' )
	{
		try
		{
			$module->parseLogic( $_POST['query_where'], false, false, false );
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in condition logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of sorting logic.
	if ( $validationMsg == '' && $_POST['query_orderby'] != '' )
	{
		try
		{
			$orderby = $_POST['query_orderby'];
			if ( strtolower( substr( rtrim( $orderby ), -5 ) ) == ' desc' )
			{
				$orderby = substr( rtrim( $orderby ), 0, -5 );
			}
			$module->parseLogic( $orderby, false, false, false );
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in sorting logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of field name/logic.
	$hasGroupingUnselected = false;
	$hasGroupingSelected = false;
	if ( $validationMsg == '' )
	{
		try
		{
			foreach ( $_POST['query_select_field'] as $index => $fieldName )
			{
				if ( $fieldName != '' )
				{
					$module->parseLogic( $fieldName, false, false, false );
					if ( $_POST['query_grouping'][ $index ] == '' )
					{
						$hasGroupingUnselected = true;
					}
					if ( $_POST['query_grouping'][ $index ] != '' )
					{
						$hasGroupingSelected = true;
					}
				}
			}
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in field name/logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of field name/logic.
	if ( $validationMsg == '' && $hasGroupingUnselected && $hasGroupingSelected )
	{
		$validationMsg = 'If grouping is selected, it must be selected for all fields';
	}
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_SYSQUERYCHK'] ) )
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
	$reportData = [ 'desc' => $_POST['query_desc'], 'projs' => [], 'forms' => [],
	                'where' => $_POST['query_where'],
	                'orderby' => $_POST['query_orderby'], 'select' => [],
	                'nomissingdatacodes' => isset( $_POST['query_nomissingdatacodes'] ),
	                'dateformat' => $_POST['query_dateformat'] ];
	foreach ( $_POST['query_proj_alias'] as $i => $projAlias )
	{
		if ( $projAlias == '' )
		{
			continue;
		}
		if ( $_POST['query_proj_lu'][$i] == 'this' )
		{
			$reportData['projs'][] = [ 'lu' => 'this', 'alias' => $projAlias ];
		}
		else
		{
			$reportData['projs'][] = [ 'lu' => $_POST['query_proj_lu'][$i],
			                           'luv' => $_POST['query_proj_luv'][$i],
			                           'alias' => $projAlias ];
		}
	}
	foreach ( $_POST['query_form'] as $i => $formName )
	{
		if ( $formName == '' )
		{
			continue;
		}
		$reportData['forms'][] = [ 'proj' => $_POST['query_form_proj'][$i],
		                           'form' => $formName, 'alias' => $_POST['query_form_alias'][$i],
		                           'join' => ( ( $i == 0 ) ? ''
		                                                 : $_POST['query_form_join'][ $i - 1 ] ),
		                           'on' => ( ( $i == 0 ) ? ''
		                                                 : $_POST['query_form_on'][ $i - 1 ] ) ];
	}
	foreach ( $_POST['query_select_field'] as $i => $fieldName )
	{
		if ( $fieldName == '' )
		{
			continue;
		}
		$reportData['select'][] = [ 'field' => $fieldName,
		                            'alias' => $_POST['query_select_alias'][$i],
		                            'grouping' => $_POST['query_grouping'][$i] ];
	}
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}



function writeProjectRow( $projType, $projVal, $aliasVal )
{
	global $module;
?>
      <td style="width:60px">
       <select name="query_proj_lu[]"
               onchange="$(this).closest('tr').find('[name=&quot;query_proj_luv[]&quot;]')
                         .css('display',$(this).val()=='this'?'none':'')">
        <option value="this">This Project</option>
        <option value="title-append"<?php echo $projType == 'title-append'
                                         ? ' selected' : ''; ?>>Title (append to this)</option>
        <option value="title"<?php echo $projType == 'title'
                                         ? ' selected' : ''; ?>>Title (exact match)</option>
        <option value="ptitle"<?php echo $projType == 'ptitle'
                                         ? ' selected' : ''; ?>>Title (partial match)</option>
        <option value="notes"<?php echo $projType == 'notes'
                                         ? ' selected' : ''; ?>>Notes (exact match)</option>
        <option value="pnotes"<?php echo $projType == 'pnotes'
                                         ? ' selected' : ''; ?>>Notes (partial match)</option>
       </select>
      </td>
      <td style="width:unset;min-width:100px">
       <input type="text" name="query_proj_luv[]"
              value="<?php echo $module->escape( $projVal ?? '' ); ?>"
              style="width:100%<?php echo $setWidths ? ';min-width:80px' : ''; ?>">
      </td>
      <td style="width:unset;max-width:20%">
       <input type="text" name="query_proj_alias[]" placeholder="alias"
              value="<?php echo $module->escape( $aliasVal ?? '' ); ?>"
              style="width:100%<?php echo $setWidths ? ';min-width:80px' : ''; ?>">
      </td>
<?php
}



function writeTableRow1( $setWidths, $projVal, $tableVal, $aliasVal, $joinVal = '' )
{
	global $module, $listTables;

?>
      <td style="text-align:center;width:15px;padding-right:0px">
<?php
	if ( ! $setWidths )
	{
?>
       <div class="instq-row-from-move" style="cursor:ns-resize">
        <i class="fas fa-arrows-up-down fs11"></i>
       </div>
<?php
	}
?>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '60px' : 'unset'; ?>">
<?php
	if ( ! $setWidths )
	{
?>
       <select name="query_form_join[]">
        <option value="inner">Inner Join</option>
        <option value="left"<?php echo $joinVal == 'left' ? ' selected' : ''; ?>>Left Join</option>
       </select>
<?php
	}
?>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '60px' : 'unset'; ?>">
       <select name="query_form_proj[]">
        <option value=""></option>
<?php
	if ( $projVal != '' )
	{
		echo '        <option selected value="', $module->escape( $projVal ), '">',
		     $module->escape( $projVal ), "</option>\n";
	}
?>
       </select>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '60px' : 'unset'; ?>">
       <select name="query_form[]">
        <option value=""></option>
<?php
	foreach ( array_keys( $listTables ) as $tableName )
	{
		echo '        <option', ( $tableVal == $tableName ? ' selected' : '' ), ' value="',
		     $module->escape( $tableName ), '">', $module->escape( substr( $tableName, 1 ) ),
		     "</option>\n";
	}
?>
       </select>
      </td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_form_alias[]" placeholder="alias"
              value="<?php echo $module->escape( $aliasVal ?? '' ); ?>"
              style="width:100%<?php echo $setWidths ? ';min-width:80px' : ''; ?>">
      </td>
<?php
}



function writeTableRow2( $onCondVal, $formVal, $firstFormVal, $aliasVal, $firstAliasVal )
{
	global $module;
	$formVal = ( $aliasVal == '' ) ? $formVal : $aliasVal;
	$firstFormVal = ( $firstAliasVal == '' ) ? $firstFormVal : $firstAliasVal;

?>
      <td style="width:unset"></td>
      <td style="text-align:left;width:unset">On Condition</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_form_on[]" placeholder="condition logic"
              style="width:100%;max-width:unset"
              value="<?php echo $module->escape( $onCondVal ?? '' ); ?>">
      </td>
<?php
}



function writeSelectRow( $setWidths, $fieldVal, $aliasVal, $groupVal )
{
	global $module;
?>

      <td style="text-align:center;width:15px;padding-right:0px">
       <div class="instq-row-select-move" style="cursor:ns-resize">
        <i class="fas fa-arrows-up-down fs11"></i>
       </div>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '50%;max-width:425px' : 'unset'; ?>">
       <input type="text" name="query_select_field[]" placeholder="field name/logic"
              value="<?php echo $module->escape( $fieldVal ?? '' ); ?>"
              data-list="field-var-list" style="width:100%">
      </td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_select_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escape( $aliasVal ?? '' ); ?>"
              style="width:100%<?php echo $setWidths ? ';min-width:60px' : ''; ?>">
      </td>
      <td style="text-align:left;width:unset">
       <select name="query_grouping[]"<?php echo $groupVal == ''
                                                 ? ' style="font-style:italic"' : '', "\n"; ?>
               onchange="$(this).val()==''?$(this).css('font-style','italic')
                                          :$(this).css('font-style','')">
        <option value="" style="font-style:italic">grouping (optional)</option>
<?php
	foreach ( [ 'this' => 'group by this field',
	            'max' => 'maximum value',
	            'mean' => 'mean average',
	            'median' => 'median average',
	            'min' => 'minimum value',
	            'percent' => 'percentage',
	            'sum' => 'sum of all values' ]
	          as $optVal => $optLbl )
	{
?>
        <option <?php echo $groupVal == $optVal ? 'selected ' : ''; ?>value="<?php echo $optVal; ?>"
                style="font-style:normal"><?php echo $optLbl; ?></option>
<?php
	}
?>
       </select>
      </td>
<?php
}



// Get list of table names and fields.
$listTables = [];
$queryTables = $module->query( "SELECT TABLE_NAME, GROUP_CONCAT(COLUMN_NAME SEPARATOR ',') fields" .
                               " FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND " .
                               "TABLE_NAME IN( SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS " .
                               "WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'project_id' AND " .
                               "DATA_TYPE = 'int' AND TABLE_NAME LIKE 'redcap\_%' AND TABLE_NAME " .
                               "NOT REGEXP '^redcap_(data|log_event)[0-9]+$' ) AND COLUMN_NAME " .
                               "<> 'project_id' GROUP BY TABLE_NAME",
                               [ $GLOBALS['db'], $GLOBALS['db'], ] );
while ( $infoTable = $queryTables->fetch_assoc() )
{
	if ( $infoTable['TABLE_NAME'] == 'redcap_data' )
	{
		$infoTable['fields'] = '';
	}
	$listTables[ substr( $infoTable['TABLE_NAME'], 6 ) ] = explode( ',', $infoTable['fields'] );
}



// Get fields and smart variables for field suggestions.
$smartVarsInfo = \Piping::getSpecialTagsInfo();
$listSmartVars = array_merge( array_keys( $smartVarsInfo[ $GLOBALS['lang']['global_17'] ] ),
                              array_keys( $smartVarsInfo[ $GLOBALS['lang']['global_156'] ] ),
                              [ 'is-download', 'is-api' ] );
array_walk( $listSmartVars, function( &$i ) { $i = '[' . $i . ']'; } );




// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit System Query Report: <?php
echo $module->escape( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' );
?>" class=""><i class="fas fa-arrow-left fs11"></i> Back to edit reports</a>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php echo $module->getUrl( 'README-InstrumentQ.md' );
?>" target="_blank"><i class="fas fa-book fs11"></i> View Documentation</a>
</p>
<form method="post" id="queryform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, true,
                                          [ 'saveable', 'image', 'api', 'public' ] ); ?>
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>Description</td>
   <td>
    <textarea name="query_desc" style="height:70px;max-width:95%;white-space:pre"><?php
echo $reportData['desc'] ?? ''; ?></textarea>
    <br>
    <span class="field-desc">
     Optional. If specified, displays this text above the report.
     Supports &lt;a&gt;, &lt;b&gt; and &lt;i&gt; HTML tags.
    </span>
   </td>
  </tr>
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
   <td>Projects</td>
   <td>
    <table id="proj-entries-tbl" style="width:95%;max-width:750px">
     <tbody>
      <tr><?php
writeProjectRow( $reportData['projs'][0]['lu'], $reportData['projs'][0]['luv'],
                 $reportData['projs'][0]['alias'] );
?></tr>
<?php
$firstProj = true;
foreach ( $reportData['projs'] as $projData )
{
	if ( $firstProj )
	{
		$firstProj = false;
		continue;
	}
?>
      <tr><?php
	writeProjectRow( $projData['lu'], $projData['luv'], $projData['alias'] );
?></tr>
<?php
}
?>
      <tr style="display:none"><?php writeProjectRow( '', '', '' ); ?></tr>
     </tbody>
    </table>
    <span id="proj-entries-link" style="display:none">
     <a onclick="$('#proj-entries-tbl tr').last().clone().css('display',''
                    ).insertBefore($('#proj-entries-tbl tr').last());return false"
        href="#" class=""><i class="mod-advrep-icon-plus"></i> Add project</a>
    </span>
   </td>
  </tr>
  <tr>
   <td>Tables or<br>Instruments</td>
   <td>
    <table id="inst-entries-tbl" style="width:95%;max-width:750px">
     <tbody>
      <tr><?php
writeTableRow1( true, $reportData['forms'][0]['proj'], $reportData['forms'][0]['form'],
                $reportData['forms'][0]['alias'] );
?></tr>
     </tbody>
<?php
$firstForm = true;
foreach ( $reportData['forms'] as $formData )
{
	if ( $firstForm )
	{
		$firstForm = false;
		continue;
	}
?>
     <tbody class="instq-row-from">
      <tr><?php
	writeTableRow1( false, $formData['proj'], $formData['form'], $formData['alias'],
	                $formData['join'] );
?></tr>
      <tr><?php
	writeTableRow2( $formData['on'], $formData['form'], $reportData['forms'][0]['form'],
	                $formData['alias'], $reportData['forms'][0]['alias'] );
?></tr>
     </tbody>
<?php
}
?>
     <tbody class="instq-row-from" style="display:none">
      <tr><?php writeTableRow1( false, '', '', '' ); ?></tr>
      <tr><?php
writeTableRow2( '', '', $reportData['forms'][0]['form'],
                '', $reportData['forms'][0]['alias'] );
?></tr>
     </tbody>
    </table>
    <span id="inst-entries-link" style="display:none">
     <a onclick="$('#inst-entries-tbl tbody').last().clone().css('display',''
                    ).insertBefore($('#inst-entries-tbl tbody').last());return false"
        href="#" class=""><i class="mod-advrep-icon-plus"></i> Add table or instrument</a>
     <br>
     <span style="font-size:0.8em">
      Choose the <i>data</i> table to add an instrument.<br>
      Inner Join will only include rows where there is data on both sides of the join.<br>
      Left Join will include rows where there is data before the join, even if there is no data
      after the join.
     </span>
    </span>
   </td>
  </tr>
  <tr>
   <td>Condition</td>
   <td>
    <input type="text" name="query_where" style="width:95%;max-width:750px"
           placeholder="condition logic"
           value="<?php echo $module->escape( $reportData['where'] ?? '' ); ?>">
   </td>
  </tr>
  <tr>
   <td>Sorting</td>
   <td>
    <input type="text" name="query_orderby" style="width:95%;max-width:750px"
           placeholder="sorting logic" list="field-var-list-sort"
           value="<?php echo $module->escape( $reportData['orderby'] ?? '' ); ?>">
   </td>
  </tr>
  <tr>
   <td>Fields to display</td>
   <td style="padding:0px">
    <table id="field-entries-tbl" style="width:95%;max-width:750px">
     <tr class="instq-row-select"><?php
writeSelectRow( true, $reportData['select'][0]['field'], $reportData['select'][0]['alias'],
                $reportData['select'][0]['grouping'] );
?></tr>
<?php
$firstField = true;
foreach ( $reportData['select'] as $fieldData )
{
	if ( $firstField )
	{
		$firstField = false;
		continue;
	}
?>
     <tr class="instq-row-select"><?php
	writeSelectRow( false, $fieldData['field'], $fieldData['alias'], $fieldData['grouping'] );
?></tr>
<?php
}
?>
     <tr class="instq-row-select" style="display:none"><?php
writeSelectRow( false, '', '', '' );
?></tr>
    </table>
    <span id="field-entries-link" style="display:none">
     &nbsp;
     <a onclick="$('#field-entries-tbl tr').last().clone().css('display',''
                    ).insertBefore($('#field-entries-tbl tr').last());$('#field-entries-tbl tr')
                    .eq(-2).find('[name=&quot;query_select_field[]&quot;]').combobox();return false"
        href="#" class=""><i class="mod-advrep-icon-plus"></i> Add field</a>
    </span>
   </td>
  </tr>
  <tr>
   <td>Hide missing data codes</td>
   <td>
    <input type="checkbox" name="query_nomissingdatacodes"<?php
echo $reportData['nomissingdatacodes'] ? ' checked' : '';
?>>
   </td>
  </tr>
  <tr>
   <td>Date display format</td>
   <td>
    <select name="query_dateformat">
     <option value=""<?php echo $reportData['dateformat'] ?? '' == '' ? ' selected' : ''?>>
      Date fields (labels) in user's preferred format
     </option>
     <option value="upf"<?php echo $reportData['dateformat'] ?? '' == 'upf' ? ' selected' : ''?>>
      All date values in user's preferred format
     </option>
     <option value="dmy"<?php echo $reportData['dateformat'] ?? '' == 'dmy' ? ' selected' : ''?>>
      All date values in D-M-Y format
     </option>
     <option value="mdy"<?php echo $reportData['dateformat'] ?? '' == 'mdy' ? ' selected' : ''?>>
      All date values in M-D-Y format
     </option>
    </select>
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
<?php $module->outputComboboxJS(); ?>
<script type="text/javascript">
 (function ()
 {
   $('#proj-entries-link, #inst-entries-link, #field-entries-link').css('display','')
   $('[name="query_proj_lu[]"]').trigger('change')
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
                        headers : { 'X-RC-AdvRep-SysQueryChk' : '1' },
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
                            var vMsg = 'Invalid system query: ' + result
                            $('#query_err_msg span').text( vMsg )
                            $('#query_err_msg').css( 'display', '' )
                          }
                        }
             } )
     return false
   }
   var vListProjs = []
   var vFuncUpdateProjs = function()
   {
     vListProjs = []
     $('[name="query_proj_alias[]"]').each(function()
     {
       if ( $(this).val() != '' )
       {
         vListProjs.push($(this).val())
       }
     })
     $('[name="query_form_proj[]"]').each(function()
     {
       var vDropdown = $(this)
       var vOldVal = ( vDropdown.val() == '' ? null : vDropdown.val() )
       vDropdown.html('<option></option>')
       $(vListProjs).each(function()
       {
         vDropdown.append($('<option></option>').attr('value',this).text(this))
       })
       if ( vOldVal != null )
       {
         vDropdown.val(vOldVal)
       }
     })
   }
   vFuncUpdateProjs()
   $('[name="query_proj_alias[]"]').on('change',vFuncUpdateProjs)
   var vSmartVars = <?php echo json_encode( $listSmartVars ), "\n"; ?>
   var vFormVars = <?php echo json_encode( $listTables ), "\n"; ?>
   var vFuncUpdateVars = function()
   {
     $('#field-var-list').html('')
     $('#field-var-list-sort').html('')
     var vFormElems = $('[name="query_form[]"]')
     var vAliasElems = $('[name="query_form_alias[]"]')
     var vOnCondElems = $('[name="query_form_on[]"]')
     var vFirstAlias = vAliasElems[0].value == '' ? vFormElems[0].value : vAliasElems[0].value
     for ( var vIndex = 0; vIndex < vFormElems.length; vIndex++ )
     {
       var vFormName = vFormElems[ vIndex ].value
       if ( vFormName == '_data' )
       {
         vFormName = prompt('Please enter instrument form name:')
         vFormName = ( vFormName == null ? '' : vFormName.replace(/^_+/,'') )
         var vItem = $(vFormElems[ vIndex ]).find('[value="' + vFormName + '"]')
         if ( vItem.length == 0 )
         {
           $(vFormElems[ vIndex ]).append($('<option></option>').attr('value',vFormName)
                                                                .text('data:'+vFormName))
         }
         $(vFormElems[ vIndex ]).val(vFormName)
       }
       var vAlias = vAliasElems[ vIndex ].value
       if ( vAlias == '' || vFormName == '' || vFormName[0] != '_' )
       {
         continue
       }
       vFormVars[ vFormName ].forEach( function( vItem )
       {
         $('<option></option>').text( '[' + vAlias + '][' + vItem + ']'
                                                        ).appendTo( $('#field-var-list') )
         $('<option></option>').text( '[' + vAlias + '][' + vItem + ']'
                                                        ).appendTo( $('#field-var-list-sort') )
         $('<option></option>').text( '[' + vAlias + '][' + vItem + '] DESC'
                                                        ).appendTo( $('#field-var-list-sort') )
       } )
     }
     vSmartVars.forEach( function( vItem )
     {
       $('<option></option>').text( vItem ).appendTo( $('#field-var-list') )
     } )
   }
   vFuncUpdateVars()
   $('[name="query_form[]"]:visible').change( vFuncUpdateVars )
   $('[name="query_form_alias[]"]:visible').keyup( vFuncUpdateVars )
   setTimeout( function()
   {
     $('[name="query_select_field[]"]:visible').combobox()
   }, 2000 )
   $('[name="query_form[]"]').css( 'max-width', '450px' )

   var vFuncMakeDraggable = function( vClass, vFirst = false )
   {
     if ( vClass == '.instq-row-from' && ! vFirst )
     {
       $('[name="query_form[]"]:visible').last().change( vFuncUpdateVars )
       $('[name="query_form_alias[]"]:visible').last().keyup( vFuncUpdateVars )
     }
     $(vClass).draggable( {
       axis: 'y',
       handle: vClass + '-move',
       revert: true,
       revertDuration: 0,
       start: function (ev, ui)
       {
         $(vClass).last().css('opacity','0').css('display','')
       },
       stop: function (ev, ui)
       {
         $(vClass).last().css('opacity','').css('display','none')
       },
       zIndex: 1000
     } )
     $(vClass).droppable( {
       accept: vClass,
       addClasses: false,
       drop: function (ev, ui)
       {
         $(ev.target).css('border-top','')
         $(ui.draggable).insertBefore($(ev.target))
       },
       over: function (ev, ui)
       {
         $(ev.target).css('border-top','2px solid #000')
       },
       out: function (ev, ui)
       {
         $(ev.target).css('border-top','')
       }
     } )
   }
   vFuncMakeDraggable('.instq-row-from', true)
   vFuncMakeDraggable('.instq-row-select', true)
   $('#proj-entries-link a').click(function()
   {
     $('[name="query_proj_alias[]"]:visible').last().on('change',vFuncUpdateProjs)
   })
   $('#inst-entries-link a').click(function(){vFuncMakeDraggable('.instq-row-from')})
   $('#field-entries-link a').click(function(){vFuncMakeDraggable('.instq-row-select')})
 })()
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
