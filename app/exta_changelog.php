<?php

require_once( "exta.php" );
require_once( "exta_context.php" );

class ExtaChangelogFactory extends ExtaContextFactory
{
    protected function createContext( ExtaApp $app, array $options ) : ExtaContext
    {
        return new ExtaChangelog( $app, $options );
    }
}

class ExtaChangelog extends ExtaContext
{
    public function __construct( ExtaApp $app, array $options )
    {
        parent::__construct( $app, $options );
    }

    protected function DispatchHandler() : bool
    {
        if( $this->_opts->Exists( "refresh" ) )
        {
            return $this->HandleRefresh();
        }
        elseif( $this->_opts->Exists( "json" ) )
        {
            return $this->HandleJson();
        }
        $this->_app->Welcome( "Changelog" );
        $this->HandlePrint();
        return true;
    }

    private function HandleRefresh() : bool
    {
        $aChangeLog = [];
        foreach( [ "release", "beta" ] as $channel )
        {
            $aChannelChangeLog = $this->GetChannelLog( $channel );
            $aChangeLog = $this->ChangeLogMerge( $aChangeLog, $aChannelChangeLog );
            $aChannelChangeLog = $this->ChangeLogFix( $aChannelChangeLog );
            file_put_contents( $this->GetFilename( $channel ), json_encode( $aChannelChangeLog, JSON_PRETTY_PRINT ) );
        }

        $aChangeLog = $this->ChangeLogFix( $aChangeLog );
        file_put_contents( $this->GetFilename(), json_encode( $aChangeLog, JSON_PRETTY_PRINT ) );

        return true;
    }

    private function GetChannelLog( $channel ) : array
    {
        $aChannelLog = [];
        $beta_channel = ( str_starts_with( $channel, "beta" ) || str_starts_with( $channel, "all" ) );
        if( ( ( $sUrl = $this->GetUrl( $beta_channel ) ) !== false ) && ( ( $jsChangelog = $this->GetResource( $sUrl ) ) !== false ) )
        {
            $objChangelog = json_decode( $jsChangelog );
            $aChannelLog = $this->ChangelogParse( $objChangelog, "Typ urządzenia", $channel );
        }
        return $aChannelLog;
    }

    private function GetUrl( bool $beta_channel ) : string|bool
    {
        $apiKey = $this->_opts->ReadStr( "apikey" );
        if( !empty( $apiKey ) )
        {
            $sSuffix = "";
            $sChangelogID = ZA_CHANGELOG_ID_RELEASE;
            if( $beta_channel )
            {
                $sSuffix = "_beta";
                $sChangelogID = ZA_CHANGELOG_ID_BETA;
            }
            return sprintf( CHANGELOG_URL, $sChangelogID, $sSuffix, $apiKey );
        }
        return false;
    }

    private function ChangelogParse( object $objChangelog, string $search, string $def_channel ) : array
    {
        $aResult = [];
        $iRow = 0;
        $bProcess = false;
        $sDevice = "";
        $ver_int = 0;
        $sVersion = "";
        $iTime = 0;
        $channel = $def_channel;
        while( $iRow < count( $objChangelog->values ) )
        {
            $aRow = $objChangelog->values[ $iRow ];
            if( count( $aRow ) > 0 )
            {
                if( strcasecmp( $aRow[ 0 ], $search ) == 0 )
                {
                    $bProcess = true;
                }
                elseif( $bProcess )
                {
                    if( !empty( $aRow[ 0 ] ) )
                    {
                        $sDevice = str_replace( "-", "", $aRow[ 0 ] );
                    }
                    if( !empty( $aRow[ 1 ] ) )
                    {
                        $channel = $def_channel;
                        $ver_int = $this->VerFromStr( $aRow[ 1 ], $channel );
                        $sVersion = $this->VerToStr( $ver_int );
                    }

                    if( !empty( $aRow[ 2 ] ) )
                    {
                        $iTime = str_replace( "\n", " ", $aRow[ 2 ] );
                        if( preg_match("/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{2,4})/", $iTime, $matches)===1)
                        {
                            $iTime = mktime(0, 0, 0, intval($matches[2]), intval($matches[1]), intval($matches[3]) );
                        }
                        elseif( preg_match("/([0-9]{1,2})\.([0-9]{2,4})/", $iTime, $matches)===1)
                        {
                            $iTime = mktime(0, 0, 0, intval($matches[1]), 1, intval($matches[2]) );
                        }
                        else
                        {
                            die($iTime);
                        }
                    }

                    if( !empty( $sDevice ) && !empty( $sVersion ) && ( $iTime > 0 ) && !empty( $aRow[ 3 ] ) )
                    {
                        if( !isset( $aResult[ $sDevice ] ) )
                        {
                            $aResult[ $sDevice ] = [];
                        }
                        if( !isset( $aResult[ $sDevice ][ $sVersion ] ) )
                        {
                            $aResult[ $sDevice ][ $sVersion ] = [];
                            $aResult[ $sDevice ][ $sVersion ] = [ "ver_str" => $sVersion, "ver_int" => $ver_int, "channel" => $channel, "time" => $iTime, "info" => [], "info_src" => [], "note" => "" ];
                        }

                        if( str_starts_with( trim( $aRow[ 3 ] ), "UWAGA" ) )
                        {
                            $aResult[ $sDevice ][ $sVersion ][ "note" ] = $aRow[ 3 ];
                        }
                        elseif( !empty( $aResult[ $sDevice ][ $sVersion ][ "note" ] ) )
                        {
                            $aResult[ $sDevice ][ $sVersion ][ "note" ] .= " " . $aRow[ 3 ];
                        }
                        else
                        {
                            foreach( preg_split( "/\n/", $aRow[ 3 ] ) as $line )
                            {
                                $line = trim( $line );
                                if( empty( $line ) )
                                {
                                    continue;
                                }
                                $lineCnt = count( $aResult[ $sDevice ][ $sVersion ][ "info" ] );
                                if( $lineCnt > 0 )
                                {
                                    $lineLast = $aResult[ $sDevice ][ $sVersion ][ "info" ][ $lineCnt - 1 ];
                                    if( str_starts_with( $lineLast, "-" ) && !str_starts_with( $line, "-" ) )
                                    {
                                        $aResult[ $sDevice ][ $sVersion ][ "info" ][ $lineCnt - 1 ] .= " " . $line;
                                        continue;
                                    }
                                }
                                $aResult[ $sDevice ][ $sVersion ][ "info" ][] = str_replace( "  ", " ", $line );
                                $aResult[ $sDevice ][ $sVersion ][ "info_src" ][] = $channel;
                            }
                        }
                    }
                }
            }
            $iRow++;
        }
        foreach( $aResult as $sDevice => $aVersion )
        {
            foreach( $aVersion as $sVersion => $aVersionInfo )
            {
                $aInfo = [];
                foreach( $aVersionInfo[ "info" ] as $sInfo )
                {
                    $aInfo[] = preg_replace( "/^\s*-\s*/", "", $sInfo );
                }
                $aResult[ $sDevice ][ $sVersion ][ "info" ] = $aInfo;
            }
        }
        return $aResult;
    }

    private function VerFromStr( string $version, string &$channel ) : int
    {
        if( preg_match( "/([0-9]+)\.([0-9]+)\.([0-9]+)(-(.*)){0,}/", $version, $matches ) === 1 )
        {
            if( count( $matches ) > 4 )
            {
                $channel = $matches[ 5 ];
            }
            return ( ( intval( $matches[ 1 ] ) & 0xFF ) << 24 ) | ( ( intval( $matches[ 2 ] ) & 0xFF ) << 16 ) | ( intval( $matches[ 3 ] ) & 0xFF );
        }
        return 0;
    }

    private function VerToStr( int $version ) : string
    {
        return sprintf( "%d.%d.%d", ( ( $version >> 24 ) & 0xFF ), ( ( $version >> 16 ) & 0xFF ), ( ( $version >> 0 ) & 0xFF ) );
    }

    private function ChangeLogMerge( array $aChangelog, array $aAppend ) : array
    {
        $aResult = $aChangelog;
        foreach( $aAppend as $device => $dev_version )
        {
            if( array_key_exists( $device, $aResult ) )
            {
                foreach( $dev_version as $version => $ver_info )
                {
                    if( array_key_exists( $version, $aResult[ $device ] ) )
                    {
                        for( $x = 0; $x < count( $ver_info[ "info" ] ); $x++ )
                        {
                            $bFound = false;
                            foreach( $aResult[ $device ][ $version ][ "info" ] as $info )
                            {
                                if( strcasecmp( $info, $ver_info[ "info" ][ $x ] ) == 0 )
                                {
                                    $bFound = true;
                                    break;
                                }
                            }
                            if( !$bFound )
                            {
                                $aResult[ $device ][ $version ][ "info" ][] = $ver_info[ "info" ][ $x ];
                                $aResult[ $device ][ $version ][ "info_src" ][] = $ver_info[ "info_src" ][ $x ];
                            }
                        }
                    }
                    else
                    {
                        $aResult[ $device ][ $version ] = $ver_info;
                    }
                }
            }
            else
            {
                $aResult[ $device ] = $dev_version;
            }
        }
        return $aResult;
    }

    private function ChangeLogFix( array $aChangelog ) : array
    {
        $aResult = [];
        foreach( $aChangelog as $device => $versions )
        {
            uasort( $versions, function ( $a, $b ) {
                return $b[ "ver_int" ] - $a[ "ver_int" ];
            } );
            foreach( $versions as $version )
            {
                $info = [];
                for( $x = 0; $x < count( $version[ "info" ] ); $x++ )
                {
                    $info[] = [ "text" => $version[ "info" ][ $x ], "channel" => $version[ "info_src" ][ $x ] ];
                }
                $version[ "info" ] = $info;
                unset( $version[ "info_src" ] );
                $aResult[ $device ][] = $version;
            }
        }
        uksort( $aResult, function ( $a, $b ) {
            return strcasecmp( $a, $b );
        } );
        return $aResult;
    }

    private function GetFilename( string $channel = "" ) : string
    {
        if( $channel == "" )
        {
            return ExtaTool::PathCombine( $this->GetBasePath(), "changelog.json" );
        }
        return ExtaTool::PathCombine( $this->GetBasePath(), sprintf( "changelog-%s.json", $channel ) );
    }

    private function GetBasePath() : string
    {
        return $this->_opts->ReadStr( "repo_base", ExtaTool::PathCombine( "repo" ) );
    }

    private function HandleJson()
    {
        $filename = $this->GetFilename( $this->_opts->ReadStr( "channel" ) );
        if( file_exists( $filename ) )
        {
            echo file_get_contents( $filename );
            return true;
        }
        return false;
    }

    private function HandlePrint() : bool
    {
        $filename = $this->GetFilename( $this->_opts->ReadStr( "channel" ) );
        $aDevices = $this->_opts->ReadArr("device");
        if( file_exists( $filename ) )
        {
            $objChangelog = json_decode( file_get_contents( $filename ) );
            foreach( $objChangelog as $device => $versions )
            {
                if( count( $aDevices) > 0 )
                {
                    $match = false;
                    foreach($aDevices as $sDevice )
                    {
                        if( str_starts_with(strtoupper($device), strtoupper($sDevice)))
                        {
                            $match = true;
                            break;
                        }
                    }
                    if( !$match )
                    {
                        continue;
                    }
                }
                if( ExtaTool::IsConsole() )
                {
                    echo "$device\n";
                    foreach( $versions as $version )
                    {
                        echo sprintf( "  * %s - %s, %d dni temu\n", $version->ver_str, date( "Y.m.d", $version->time ), intval( ( time() - $version->time ) / 86400 ) );
                        foreach( $version->info as $info )
                        {
                            echo sprintf( "    - %s\n", $info->text );
                        }
                        if( !empty($version->note) )
                        {
                            echo sprintf("\n    %s\n", $version->note);
                        }
                        echo "\n";
                    }
                    echo "\n";
                }
                else
                {
                    echo "<h1>$device</h1>";
                    echo "<ul>";
                    foreach( $versions as $version )
                    {
                        echo "<li><h3 style='color:" . ( $version->channel == "release" ? "green" : "red" ) . "'>" . $version->ver_str . "<span style='font-size: 90%;'> - " . date( "Y.m.d", $version->time ) . " - " . intval( ( time() - $version->time ) / 86400 ) . " dni temu</span></h3>";
                        echo "<ul>";
                        foreach( $version->info as $info )
                        {
                            echo sprintf( "<li style='color:%s;'>%s</li>", ( $info->channel == "release" ? "green" : "red" ), $info->text );
                        }
                        echo "</ul><p />";
                        echo "</li>";
                    }
                    echo "</ul>";
                }
            }
        }
        return true;
    }
}

ExtaApp::RegisterContext( "changelog", new ExtaChangelogFactory() );
