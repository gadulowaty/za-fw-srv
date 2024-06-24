<?php

require_once( dirname( __FILE__ ) . "/../app/exta.php" );

// parse_str($_SERVER[ "QUERY_STRING" ], $_GET );

if( isset( $_GET[ "__path" ] ) )
{
    $svcPath = $_GET[ "__path" ];
    if( $svcPath == "" )
    {
        $svcPath = "root";
    }
    unset( $_GET[ "__path" ] );
}

if( isset( $_GET[ "__repo" ] ) )
{
    $sysRepo = empty( $_GET[ "__repo" ] ) ? EXTA_DEFAULT_REPO : $_GET[ "__repo" ];
    unset( $_GET[ "__repo" ] );
}
else
    $sysRepo = EXTA_DEFAULT_REPO;

$extaApp = ExtaApp::GetApp( $sysRepo );
if( isset( $svcPath ) )
{
    $extaApp->ServiceDispatch( $svcPath, $_GET );
}
else
{
    $extaApp->Welcome();
}

?>