<?php

require_once( "exta_crc16.php" );

class ExtaFwSrvClient
{
    private const EXTA_FW_CMD_LIST = "list";
    private const EXTA_FW_CMD_FETCH = "name";

    private string $_server_addr;
    private array $_http_opts;
    private ExtaOpts $_opts;
    private ExtaCache $_cache;

    public function __construct( string $server_addr, array $http_opts, array $options, ExtaCache $cache = null )
    {
        $this->_server_addr = $server_addr;
        $this->_http_opts = $http_opts;
        $this->_opts = new ExtaOpts( $options );
        $this->_cache = $cache;
    }

    public function GetList() : array|bool
    {
        $aFwSrvList = [];
        $sUrl = $this->GetUrl( ExtaFwSrvClient::EXTA_FW_CMD_LIST );
        if( ( $jsList = $this->GetResource( $sUrl ) ) !== false )
        {
            if( ( $objList = json_decode( $jsList ) ) === null )
            {
                return false;
            }
            foreach( $objList->name as $sItem )
            {
                if( ( preg_match( "/([^_]+)_([0-9]+)_([0-9]+)_([0-9]+)_([0-9]+).bin/", $sItem, $aMatches ) === 1 ) && ( $aMatches[ 0 ] == $sItem ) )
                {
                    $aFwSrvEntry = [
                        "name" => $sItem,
                        "device" => $aMatches[ 1 ],
                        "version" => [
                            "major" => $aMatches[ 2 ],
                            "minor" => $aMatches[ 3 ],
                            "build" => $aMatches[ 4 ],
                            "release" => $aMatches[ 5 ],
                        ]
                    ];

                    $sUrl = $this->GetUrl( ExtaFwSrvClient::EXTA_FW_CMD_FETCH, [ "name" => $aFwSrvEntry[ "device" ] ] );
                    if( ( $jsInfo = $this->GetResource( $sUrl ) ) !== false )
                    {
                        $objInfo = json_decode( $jsInfo );

                        $aFwSrvEntry[ "name_srv" ] = $objInfo->name;
                        $aFwSrvEntry[ "size" ] = $objInfo->size;
                        $aFwSrvEntry[ "version" ][ "str" ] = $objInfo->version;
                        $aFwSrvEntry[ "crc_16" ] = $objInfo->crc_16;
                        $aFwSrvList[] = $aFwSrvEntry;
                    }
                }
                else
                {
                    ExtaTool::ConOut( "ERROR: format error for '%s'\n", $sItem );
                }
            }
        }
        return $aFwSrvList;
    }

    private function GetUrl( string $sAction, array $options = [] ) : string
    {
        $opts = new ExtaOpts( $this->_opts->Merge( $options ) );

        $sUrl = sprintf( "http://%s:4040/firmware/?%s", $this->_server_addr, $sAction );
        if( $sAction == ExtaFwSrvClient::EXTA_FW_CMD_FETCH )
        {
            $sUrl .= sprintf( "=%s.bin", $opts->ReadStr( "name", "EFC01" ) );
            if( ( $iStart = $opts->ReadInt( "start" ) ) >= 0 )
            {
                $sUrl .= sprintf( "&start=%d", $iStart );
            }
            if( ( $iSize = $opts->ReadInt( "size" ) ) >= 0 )
            {
                $sUrl .= sprintf( "&size=%d", $iSize );
            }
        }
        $sUrl .= sprintf( "&beta_software=%s", $opts->ReadBool( "beta" ) ? "true" : "false" );
        return $sUrl;
    }

    private function GetResource( string $sUrl ) : string|bool
    {
        if( isset( $this->_cache ) )
        {
            return $this->_cache->GetResource( $sUrl, $this->_http_opts );
        }
        return ExtaCache::FetchResource( $sUrl, $this->_http_opts );
    }

    public function Download( array $aFwSrvEntry, string $path, int $iChunkMax = 8192 ) : bool
    {
        $bVerbose = $this->_opts->ReadBool("verbose" );

        $filename = "";
        $hFile = false;
        if( !empty( $path ) )
        {
            $filename = ExtaTool::PathCombine( $path, $aFwSrvEntry[ "name" ] );
            if( file_exists($filename ) && !$this->_opts->ReadBool("force" ) )
            {
                return true;
            }
            if( ( $hFile = @fopen( $filename, "wb" ) ) === false )
            {
                ExtaTool::OutOut( "ERROR: Failed to open '%s'\n", $filename );
                return false;
            }
        }

        $oFileCRC = new Exta_CRC16_mcrf();
        $iTotalSize = $aFwSrvEntry[ "size" ];
        $iOffset = 0;

        $bResult = true;
        while( $bResult && ( $iTotalSize > 0 ) )
        {
            $iChunkSize = min( $iChunkMax, $iTotalSize );
            $sUrl = $this->GetUrl( ExtaFwSrvClient::EXTA_FW_CMD_FETCH, [ "name" => $aFwSrvEntry[ "device" ], "start" => $iOffset, "size" => $iChunkSize ] );
            # ExtaTool::ConOut( "Download: %s\n", $sUrl );

            $bResult = ( $jsChunk = $this->GetResource( $sUrl ) ) !== false;
            if( $bResult )
            {
                $bResult = ( $objChunk = @json_decode( $jsChunk ) ) !== null;
                if( $bResult )
                {
                    $bBuffer = implode( array_map( "chr", $objChunk->part ) );
                    $iCRC = Exta_CRC16_mcrf::FromBuffer($bBuffer);
                    $bResult = $iCRC == $objChunk->crc16;
                    if( $bResult )
                    {
                        if( $hFile !== false )
                        {
                            $bResult = @fwrite($hFile, $bBuffer );
                            if( !$bResult )
                            {
                                ExtaTool::OutOut( "ERROR: Failed to write chunk (%d/%d)\n", $iOffset, $iChunkSize );
                            }
                        }
                        if( $bResult )
                        {
                            if( $bVerbose )
                            {
                                ExtaTool::ConOut( "Downloaded [ %06d - %06d ]: Length %d byte(s), crc 0x%04x\n", $iOffset, $iChunkSize, strlen( $jsChunk ), $objChunk->crc16 );
                            }
                            $oFileCRC->CalcBuffer( $bBuffer );
                        }
                    }
                    else
                    {
                        ExtaTool::OutOut( "ERROR: Chunk checksum is invalid (%d/%d)\n", $iOffset, $iChunkSize );
                    }
                }
                else
                {
                    ExtaTool::OutOut( "ERROR: JSON chunk is malformed (%d/%d)\n", $iOffset, $iChunkSize );
                }
            }
            else
            {
                ExtaTool::OutOut( "ERROR: Failed to download chunk (%d/%d)\n", $iOffset, $iChunkSize );
            }
            $iOffset += $iChunkSize;
            $iTotalSize -= $iChunkSize;
        }
        if( $hFile !== false )
        {
            fclose( $hFile );
        }
        if( $bResult )
        {
            $bResult = ( $oFileCRC->Value() == $aFwSrvEntry[ "crc_16" ] );
            if( !$bResult )
            {
                ExtaTool::OutOut( "ERROR: Global CRC16 checksum error for '%s'\n", $aFwSrvEntry[ "device" ] );
            }
        }
        if( !$bResult && !empty( $filename ) )
        {
            @unlink( $filename );
        }
        return $bResult;
    }
}
