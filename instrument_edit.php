<?php
/**
 *	Instrument Query Reports edit page.
 */

namespace Nottingham\AdvancedReports;



// Check user can edit Instrument Query reports and verify the report exists and is an Instrument
// Query report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'instrument' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'instrument' )
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
	foreach ( $_POST['query_form'] as $i => $formName )
	{
		if ( $formName == '' &&
		     ( $i == 0 || $_POST['query_form_alias'][$i] != '' ||
		       $_POST['query_form_on'][ $i - 1 ] != '' ) )
		{
			$validationMsg =
					'Form cannot be empty if the first entry or if alias/condition specified.';
			break;
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
	if ( $validationMsg == '' )
	{
		try
		{
			foreach ( $_POST['query_select_field'] as $fieldName )
			{
				if ( $fieldName != '' )
				{
					$module->parseLogic( $fieldName, false, true, false );
				}
			}
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in field name/logic - ' . $e->getMessage();
		}
	}
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_INSTQUERYCHK'] ) )
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
	$reportData = [ 'desc' => $_POST['query_desc'], 'forms' => [], 'where' => $_POST['query_where'],
	                'orderby' => $_POST['query_orderby'], 'select' => [],
	                'nomissingdatacodes' => isset( $_POST['query_nomissingdatacodes'] ) ];
	foreach ( $_POST['query_form'] as $i => $formName )
	{
		if ( $formName == '' )
		{
			continue;
		}
		$reportData['forms'][] = [ 'form' => $formName, 'alias' => $_POST['query_form_alias'][$i],
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
		                            'alias' => $_POST['query_select_alias'][$i] ];
	}
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}



// Get fields and smart variables for field suggestions.
$smartVarsInfo = \Piping::getSpecialTagsInfo();
$listSmartVars = array_merge( array_keys( $smartVarsInfo[ $GLOBALS['lang']['global_17'] ] ),
                              array_keys( $smartVarsInfo[ $GLOBALS['lang']['global_156'] ] ),
                              [ 'is-download' ] );
array_walk( $listSmartVars, function( &$i ) { $i = '[' . $i . ']'; } );
$listFormVars = [];
foreach ( array_keys( $module->getInstrumentList() ) as $instrument )
{
	$listFormVars[ $instrument ] = array_values(
	                                array_unique( array_merge( [ \REDCap::getRecordIdField() ],
	                                                    \REDCap::getFieldNames( $instrument ) ) ) );
}




// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit Instrument Query Report: <?php
echo $module->escapeHTML( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' );
?>" class=""><i class="fas fa-arrow-circle-left fs11"></i> Back to edit reports</a>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php echo $module->getUrl( 'README-InstrumentQ.md' );
?>" target="_blank"><i class="fas fa-book fs11"></i> View Documentation</a>
</p>
<form method="post" id="queryform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, true, 'image' ); ?>
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
   <td>Instruments</td>
   <td>
    <table id="inst-entries-tbl" style="width:95%;max-width:550px">
     <tr>
      <td style="text-align:left;width:60px"></td>
      <td style="text-align:left;width:60px"><?php
$module->outputInstrumentDropdown( 'query_form[]', $reportData['forms'][0]['form'] ?? '' );
?></td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_form_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escapeHTML( $reportData['forms'][0]['alias'] ?? '' ); ?>"
              style="width:100%">
      </td>
     </tr>
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
     <tr>
      <td style="text-align:left;width:unset">JOIN</td>
      <td style="text-align:left;width:unset"><?php
	$module->outputInstrumentDropdown( 'query_form[]', $formData['form'] );
?></td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_form_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escapeHTML( $formData['alias'] ); ?>" style="width:100%">
      </td>
     </tr>
     <tr>
      <td style="text-align:left;width:unset">ON</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_form_on[]" placeholder="condition logic" style="width:100%"
              value="<?php echo $module->escapeHTML( $formData['on'] ); ?>">
      </td>
     </tr>
<?php
}
?>
     <tr style="display:none">
      <td style="text-align:left;width:unset">JOIN</td>
      <td style="text-align:left;width:unset"><?php
$module->outputInstrumentDropdown( 'query_form[]', '' );
?></td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_form_alias[]" placeholder="alias (optional)"
              style="width:100%">
      </td>
     </tr>
     <tr style="display:none">
      <td style="text-align:left;width:unset">ON</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_form_on[]" placeholder="condition logic" style="width:100%">
      </td>
     </tr>
    </table>
    <span id="inst-entries-link" style="display:none">
     <a onclick="$('#inst-entries-tbl tr').slice(-2).clone(true).css('display',''
                    ).insertBefore($('#inst-entries-tbl tr').slice(-2,-1));return false"
        href="#" class=""><i class="fas fa-plus-circle fs12"></i> Add instrument</a>
    </span>
   </td>
  </tr>
  <tr>
   <td>Condition</td>
   <td>
    <input type="text" name="query_where" style="width:100%" placeholder="condition logic"
           value="<?php echo $module->escapeHTML( $reportData['where'] ?? '' ); ?>">
   </td>
  </tr>
  <tr>
   <td>Sorting</td>
   <td>
    <input type="text" name="query_orderby" style="width:100%" placeholder="sorting logic"
           list="field-var-list-sort"
           value="<?php echo $module->escapeHTML( $reportData['orderby'] ?? '' ); ?>">
   </td>
  </tr>
  <tr>
   <td>Fields to display</td>
   <td style="padding:0px">
    <table id="field-entries-tbl" style="width:95%;max-width:550px">
     <tr>
      <td style="text-align:left;width:60%">
       <input type="text" name="query_select_field[]" placeholder="field name/logic"
              value="<?php echo $module->escapeHTML( $reportData['select'][0]['field'] ?? '' ); ?>"
              list="field-var-list" style="width:100%">
      </td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_select_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escapeHTML( $reportData['select'][0]['alias'] ?? '' ); ?>"
              style="width:100%">
      </td>
     </tr>
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
     <tr>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_select_field[]" placeholder="field name/logic"
              value="<?php echo $module->escapeHTML( $fieldData['field'] ); ?>"
              list="field-var-list" style="width:100%">
      </td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_select_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escapeHTML( $fieldData['alias'] ); ?>"
              style="width:100%">
      </td>
     </tr>
<?php
}
?>
     <tr style="display:none">
      <td style="text-align:left;width:unset">
       <input type="text" name="query_select_field[]" placeholder="field name/logic"
              list="field-var-list" style="width:100%">
      </td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_select_alias[]" placeholder="alias (optional)"
              style="width:100%">
      </td>
     </tr>
    </table>
    <span id="field-entries-link" style="display:none">
     &nbsp;
     <a onclick="$('#field-entries-tbl tr').last().clone().css('display',''
                    ).insertBefore($('#field-entries-tbl tr').last());return false"
        href="#" class=""><i class="fas fa-plus-circle fs12"></i> Add field</a>
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
   $('#inst-entries-link, #field-entries-link').css('display','')
   var vValidated = false
   $('#queryform')[0].onsubmit = function()
   {
     if ( vValidated )
     {
       return true
     }
     $.ajax( { url : '<?php echo $module->getUrl( 'instrument_edit.php?report_id=' . $reportID ); ?>',
               method : 'POST',
               data : $('#queryform').serialize(),
                        headers : { 'X-RC-AdvRep-InstQueryChk' : '1' },
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
                            var vMsg = 'Invalid instrument query: ' + result
                            $('#query_err_msg span').text( vMsg )
                            $('#query_err_msg').css( 'display', '' )
                          }
                        }
             } )
     return false
   }
   var vSmartVars = <?php echo json_encode( $listSmartVars ), "\n"; ?>
   var vFormVars = <?php echo json_encode( $listFormVars ), "\n"; ?>
   var vFuncUpdateVars = function()
   {
     $('#field-var-list').html('')
     $('#field-var-list-sort').html('')
     var vFormElems = $('[name="query_form[]"]')
     var vAliasElems = $('[name="query_form_alias[]"]')
     for ( var vIndex = 0; vIndex < vFormElems.length; vIndex++ )
     {
       var vFormName = vFormElems[ vIndex ].value
       if ( vFormName == '' )
       {
         continue
       }
       var vAlias = vAliasElems[ vIndex ].value == '' ? vFormName : vAliasElems[ vIndex ].value
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
   $('[name="query_form[]"]').change( vFuncUpdateVars )
   $('[name="query_form_alias[]"]').keyup( vFuncUpdateVars )
 })()
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
