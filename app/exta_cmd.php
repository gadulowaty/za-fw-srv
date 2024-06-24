<?php

require_once( "exta.php" );
require_once( "exta_firmware.php" );
require_once( "exta_changelog.php" );

function MapCommandLine( string $input ) : string
{
    $sIdent = substr( $input, 0, 1 );
    $aIdents = [
        "d" => "device",
        "w" => "wait",
        "h" => "help",
        "s" => "server",
    ];

    foreach( $aIdents as $sIdentKey => $sIdentLong )
    {
        if( strcasecmp( $sIdent, $sIdentKey ) == 0 )
        {
            return $sIdentLong;
        }
    }
    return $input;
}

/**
 * @param int $argc
 * @param string[] $argv
 * @return string[]
 */
function ReadCommandLine( int $argc, array $argv ) : array
{
    $index = 1;
    $aResult = [];
    $last_key = "";
    while( $index < $argc )
    {
        if( preg_match( "/--([_0-9a-zA-Z]+)(=(.*))?/", $argv[ $index ], $matches ) === 1 )
        {
            if( count( $matches ) == 2 )
            {
                $aResult[ $matches[ 1 ] ] = $matches[ 1 ];
            }
            else if( count( $matches ) == 4 )
            {
                $aResult[ $matches[ 1 ] ] = $matches[ 3 ];
            }
        }
        elseif( preg_match( "/-([0-9a-zA-Z])/", $argv[ $index ], $matches ) === 1 )
        {
            $mapped = MapCommandLine( $matches[ 1 ] );
            $aResult[ $mapped ] = $mapped;
            $last_key = $mapped;
        }
        elseif( $last_key != "" )
        {
            $aResult[ $last_key ] = $argv[ $index ];
        }
        $index++;
    }
    return $aResult;
}

$aOpts = ReadCommandLine( $argc, $argv );

$svcPath = ExtaTool::VarReadStr( $aOpts, "service", "firmware" );
$sysRepo = ExtaTool::VarReadStr( $aOpts, "repo", EXTA_DEFAULT_REPO );

$extaApp = ExtaApp::GetApp( $sysRepo );
if( isset( $svcPath ) )
{
    $extaApp->ServiceDispatch( $svcPath, $aOpts );
}

