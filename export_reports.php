<?php
/*
 *	Exports the advanced reports configuration as a JSON document.
 */


if ( ! $module->framework->getUser()->isSuperUser() )
{
	exit;
}

header( 'Content-Type: application/json' );

echo json_encode( $module->getProjectSettings() );