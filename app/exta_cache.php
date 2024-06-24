<?php

class ExtaCache
{
    private array $m_aMemCache;
    private string $m_sCacheDir;
    private int $m_iAging;

    public function __construct( $sCacheDir, $iAging = 3600 )
    {
        $this->m_iAging = $iAging;
        $this->m_sCacheDir = $sCacheDir;
        $this->m_aMemCache = [];
        @mkdir( $this->m_sCacheDir, 0777, true );
    }

    public function GetResource( string $sUrl, array $aHttpOpts = [], string $sContentType = "application/json" ) : string|bool
    {
        if( ( $sResult = $this->Fetch( $sUrl ) ) !== false )
        {
            return $sResult;
        }
        $sResult = ExtaCache::FetchResource( $sUrl, $aHttpOpts, $sContentType );
        if( $sResult !== false )
        {
            $this->Store( $sUrl, $sResult );
        }
        return $sResult;
    }

    private function Fetch( string $sResource ) : string|bool
    {
        $sCacheId = $this->GetId( $sResource );

        $bResult = false;
        if( ( $aCacheEntry = $this->GetEntry( $sCacheId ) ) !== false )
        {
            $bResult = @file_get_contents( $aCacheEntry[ "file" ] );
            if( $bResult === false )
            {
                $this->Remove( $sCacheId );
            }
        }
        if( $bResult === false )
        {
            $sCacheFile = $this->GetFile( $sCacheId );
            if(
                file_exists( $sCacheFile ) && ( ( $aStat = @stat( $sCacheFile ) ) !== false ) &&
                ( $bResult = @file_get_contents( $sCacheFile ) ) !== false )
            {
                $iCacheTime = time() - $aStat[ "ctime" ];
                if( $iCacheTime > $this->m_iAging )
                {
                    @unlink( $sCacheFile );
                    $bResult = false;
                }
                else
                {
                    $this->Add( $sCacheId, $sCacheFile, $aStat[ "ctime" ] );
                }
            }
        }
        return $bResult;
    }

    private function GetId( string $sResource ) : string
    {
        return sha1( $sResource );
    }

    private function GetEntry( string $sCacheId ) : array|false
    {
        if( array_key_exists( $sCacheId, $this->m_aMemCache ) === true )
        {
            $aCacheEntry = $this->m_aMemCache[ $sCacheId ];
            if( $this->Valid( $aCacheEntry ) )
            {
                return $aCacheEntry;
            }
            $this->Remove( $sCacheId );
        }
        return false;
    }

    private function Valid( array $aCacheEntry ) : bool
    {
        $iCacheTime = time() - $aCacheEntry[ "time" ];
        return ( $iCacheTime < $this->m_iAging );
    }

    private function Remove( string $aCacheId, bool $bDelete = true ) : bool
    {
        if( ( $bResult = array_key_exists( $aCacheId, $this->m_aMemCache ) ) === true )
        {
            $bResult = true;
            if( $bDelete )
            {
                $sCacheFile = $this->m_aMemCache[ "$aCacheId" ];
                $bResult = @unlink( $sCacheFile );
            }
            if( $bResult )
            {
                unset( $this->m_aMemCache[ "$aCacheId" ] );
            }
        }
        return $bResult;
    }

    private function GetFile( string $sCacheId ) : string
    {
        return ExtaTool::PathCombine( $this->m_sCacheDir, $sCacheId );
    }

    private function Add( string $sCacheId, string $sCacheFile, int $iTime = 0 ) : bool
    {
        if( $iTime == 0 )
        {
            $iTime = time();
        }
        $this->m_aMemCache[ $sCacheId ] = [ "file" => $sCacheFile, "time" => $iTime ];
        return true;
    }

    public static function FetchResource( string $sUrl, array $aHttpOpts = [], string $sContentType = "application/json" ) : string|bool
    {
        $aHeaders = array(
            'Accept: ' . $sContentType,
            'Content-Type: ' . $sContentType,
        );

        $rCurl = curl_init();
        curl_setopt( $rCurl, CURLOPT_URL, $sUrl );
        curl_setopt( $rCurl, CURLOPT_HTTPHEADER, $aHeaders );
        curl_setopt( $rCurl, CURLOPT_TIMEOUT, 10 );
        curl_setopt( $rCurl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $rCurl, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $rCurl, CURLOPT_USERAGENT, "EFC01 by Zamel Polska" );
        curl_setopt( $rCurl, CURLOPT_MAXREDIRS, 5 );
        curl_setopt( $rCurl, CURLOPT_HEADER, false );
        curl_setopt( $rCurl, CURLOPT_HTTPGET, true );

        foreach( $aHttpOpts as $iHttpOpt => $xHttpOptValue )
        {
            curl_setopt( $rCurl, $iHttpOpt, $xHttpOptValue );
        }

        $sResult = curl_exec( $rCurl );
        $http_status = curl_getinfo( $rCurl, CURLINFO_HTTP_CODE );

        curl_close( $rCurl );

        return ( $http_status == 200 ? $sResult : false );
    }

    private function Store( $sResource, $sContents ) : bool
    {
        if( ( $this->m_iAging > 0 ) && ( $sContents != "" ) )
        {
            $sCacheId = $this->GetId( $sResource );
            $sCacheFile = $this->GetFile( $sCacheId );
            if( @file_put_contents( $sCacheFile, $sContents ) !== false )
            {
                return $this->Add( $sCacheId, $sCacheFile );
            }
        }
        return false;
    }
}
