<?php


class ExtaFwItem
{
    var $m_sDevice;
    var $m_sFileName;
    var $m_iSize;
    var $m_iModTime;
    var $m_iMagic;
    var $m_iVersion;
    var $m_iChecksum;
    var $m_iCrc16;

    private function __construct( $sDevice, $sFileName, $aFwInfo )
    {
        $this->m_sDevice = strtoupper( $sDevice );
        $this->m_sFileName = $sFileName;
        $this->m_iMagic = $aFwInfo[ "magic" ];
        $this->m_iVersion = $aFwInfo[ "version" ];
        $this->m_iChecksum = $aFwInfo[ "checksum" ];
        $this->m_iCrc16 = $aFwInfo["crc16"];
        if( ( $aStat = stat( $sFileName ) ) !== false )
        {
            $this->m_iSize = $aStat[ "size" ];
            $this->m_iModTime = $aStat[ "mtime" ];
        }
        else
        {
            $this->m_iSize = 0;
            $this->m_iModTime = 0;
        }
    }

    public static function InfoStr( $aFwInfo )
    {
        return sprintf( "magic = 0x%08X; version = 0x%08X; checksum = 0x%08X", $aFwInfo[ "magic" ], $aFwInfo[ "version" ], $aFwInfo[ "checksum" ] );
    }

    public static function Open( $sFileName )
    {
        $oFwItem = false;
        if( ( strcasecmp( pathinfo( $sFileName, PATHINFO_EXTENSION ), "bin" ) === 0 ) && is_file( $sFileName ) && ( ( $hFile = @fopen( $sFileName, "r" ) ) !== false ) )
        {
            $aFwInfo = array( "magic" => 0, "version" => 0, "checksum" => 0 );
            if( fseek( $hFile, -1 * EXTA_FW_INFO_SIZE, SEEK_END ) === 0 )
            {
                if( ( $sInfo = fread( $hFile, EXTA_FW_INFO_SIZE ) ) !== false )
                {
                    if( ( ( $aFwInfo = unpack( "V1magic/V1version/V1checksum", $sInfo ) ) !== false ) && ( ( $aFwInfo[ "magic" ] == EXTA_FW_EFC_MAGIC1 ) || ( $aFwInfo[ "magic" ] == EXTA_FW_EFC_MAGIC2 ) || ( $aFwInfo[ "magic" ] == EXTA_FW_MOD_MAGIC ) ) )
                    {
                        $matches = array();
                        $iCrc16 = 0;
                        if( ( self::ChecksumCompute( $hFile, $iCrc16 ) === $aFwInfo[ "checksum" ] ) && preg_match( '/^([A-Za-z0-9]{5,}).*/', basename( $sFileName ), $matches ) )
                        {
                            $aFwInfo["crc16"] = $iCrc16;
                            $oFwItem = new ExtaFwItem( $matches[ 1 ], $sFileName, $aFwInfo );
                        }
                    }
                }
            }
            fclose( $hFile );
        }
        return $oFwItem;
    }

    private static function ChecksumCompute( $hFile, &$iCrc16 )
    {
        return self::ChecksumFile( $hFile, $iCrc16, 4 );
    }

    private static function ChecksumFile( $hFile, &$iCrc16, $iOffset = 4 )
    {
        $crc = new Exta_CRC16_mcrf();
        $iCRC = 0xFFFFFFFF;
        if( ( ( $aStat = fstat( $hFile ) ) !== false ) && ( fseek( $hFile, 0, SEEK_SET ) === 0 ) )
        {
            $iSize = $aStat[ "size" ] - $iOffset;
            while( ( $iSize > 0 ) && ( $sValue = fread( $hFile, 4 ) ) && ( ( $iValue = @unpack( "V1value", $sValue ) ) !== false ) && ( $iCRC != 0 ) )
            {
                $crc->CalcBuffer($sValue);
                $iCRC = ExtaTool::Crc32Calc( $iCRC, $iValue[ "value" ] );
                $iSize -= 4;
            }
            $crc->CalcBuffer(fread($hFile, $iOffset));
        }
        $iCrc16 = $crc->Value();
        $iCRC = $iCRC & 0xFFFFFFFF;
        return $iCRC;
    }

    public static function OpenEx( $sFileName )
    {
        $oFwItem = false;
        if( is_file( $sFileName ) && ( ( $hFile = @fopen( $sFileName, "r" ) ) !== false ) )
        {
            if( ( ( $iOffset = self::ChecksumScan( $hFile ) ) > EXTA_FW_INFO_SIZE ) && ( ( $aFwInfo = self::ReadInfo( $hFile, $iOffset ) ) !== false ) && ( fseek( $hFile, 0, SEEK_END ) === 0 ) )
            {
                if( ( $aFwInfo[ "magic" ] == EXTA_FW_EFC_MAGIC1 ) || ( $aFwInfo[ "magic" ] == EXTA_FW_EFC_MAGIC2 ) || ( $aFwInfo[ "magic" ] == EXTA_FW_MOD_MAGIC ) )
                {
                    $matches = array();
                    if( preg_match( '/^([A-Za-z0-9]{5,}).*/', basename( $sFileName ), $matches ) )
                    {
                        $oFwItem = new ExtaFwItem( $matches[ 1 ], $sFileName, $aFwInfo );
                    }
                }
            }
            fclose( $hFile );
        }
        return $oFwItem;
    }

    private static function ChecksumScan( $hFile )
    {
        if( ( ( $aStat = fstat( $hFile ) ) !== false ) && ( fseek( $hFile, 0, SEEK_SET ) === 0 ) )
        {
            $iCRC = 0xFFFFFFFF;
            $iSize = $aStat[ "size" ];
            while( ( $iSize > 0 ) && ( $iCRC != 0 ) && ( $sValue = fread( $hFile, 4 ) ) && ( ( $iValue = @unpack( "V1value", $sValue ) ) !== false ) )
            {
                $iCRC = ExtaTool::Crc32Calc( $iCRC, $iValue[ "value" ] );
                $iSize -= 4;
            }
            if( $iCRC == 0 )
            {
                return ftell( $hFile );
            }
        }
        return 0;
    }

    public static function ReadInfo( $hFile, $iOffset )
    {
        if(
            ( ( $iOffset < EXTA_FW_INFO_SIZE ) && ( fseek( $hFile, -1 * EXTA_FW_INFO_SIZE, SEEK_END ) === 0 ) ) ||
            ( ( $iOffset >= EXTA_FW_INFO_SIZE ) && ( fseek( $hFile, $iOffset - EXTA_FW_INFO_SIZE, SEEK_SET ) === 0 ) ) )
        {
            if( ( ( $sInfo = fread( $hFile, EXTA_FW_INFO_SIZE ) ) !== false ) && ( ( $aFwInfo = unpack( "V1magic/V1version/V1checksum", $sInfo ) ) !== false ) && ( $aFwInfo[ "version" ] !== 0 ) )
            {
                return $aFwInfo;
            }
        }
        return false;
    }

    public static function Validate( $sFileName )
    {
        $lResult = false;
        if( is_file( $sFileName ) && ( ( $hFile = @fopen( $sFileName, "r" ) ) !== false ) )
        {
            $lResult = self::ValidateFile( $hFile );
            fclose( $hFile );
        }
        return $lResult;
    }

    public static function FromIndexEntry( string $sDirName, array $aIndexEntry ) : ExtaFwItem|bool
    {
        $oFwItem = new ExtaFwItem( $aIndexEntry[ "device" ], ExtaTool::PathCombine( $sDirName, $aIndexEntry[ "filename" ] ), $aIndexEntry );
        if( $oFwItem->GetSize() > 0 )
        {
            return $oFwItem;
        }
        return false;
    }

    public function GetSize()
    {
        return $this->m_iSize;
    }

    private static function ChecksumValidate( $hFile )
    {
        $iCrc16 = 0;
        return self::ChecksumFile( $hFile, $iCrc16 );
    }

    public function GetFileName()
    {
        return $this->m_sFileName;
    }

    public function GetModTime()
    {
        return $this->m_iModTime;
    }

    public function GetChecksum()
    {
        return $this->m_iChecksum;
    }

    public function GetCrc16()
    {
        return $this->m_iCrc16;
    }

    public function FixName() : bool
    {
        $new_name = str_replace( "-", "_", $this->m_sFileName );
        if( strcmp( $new_name, $this->m_sFileName ) != 0 )
        {
            rename($this->m_sFileName, $new_name);
            $this->m_sFileName = $new_name;
            return true;
        }
        return false;
    }

    public function GetNamedChunk( int $iStart, $iSize ) : array | false
    {
        $aResult = false;
        // {"name":"SRM22.bin","start":"8192","size":"8192","crc16":63097,"part":[<bytes>]}
        if( ( $hFile = fopen($this->GetFileName(), "rb" ) ) !== false )
        {
            if( @fseek($hFile, $iStart, SEEK_SET) == 0 )
            {
                if( ( $bBuffer = fread( $hFile, $iSize ) ) !== false )
                {
                    $aResult = [
                        "name" => sprintf( "%s.bin", $this->m_sDevice ),
                        "start" => $iStart,
                        "size" => $iSize,
                        "crc16" => Exta_CRC16_mcrf::FromBuffer($bBuffer),
                        "part" => ExtaTool::BufferToBytes($bBuffer)
                    ];
                }
            }
            fclose( $hFile );
        }
        return $aResult;
    }

    public function GetNamedData() : array
    {
        // {"name":"EFC01.bin","size":679420,"version":"1.6.0.29","crc_16":10261}
        return [
            "name" => sprintf( "%s.bin", $this->m_sDevice ),
            "size" => $this->m_iSize,
            "version" => $this->GetVersionStr(".", true),
            "crc_16" => $this->GetCrc16()
        ];
    }
    public function GetSerializationData()
    {

        return [ "device" => $this->m_sDevice,
            "filename" => basename( $this->m_sFileName ),
            "size" => $this->m_iSize,
            "checksum" => $this->m_iChecksum,
            "crc16" => $this->m_iCrc16,
            "version" => $this->m_iVersion,
            "mtime" => $this->m_iModTime,
            "magic" => $this->m_iMagic
        ];
    }

    public function GetVersionStr( $sSep = ".", bool $useNameVer = false )
    {
        $iVersion = 0;
        if( $useNameVer )
        {
            if( preg_match( "/[^_-]+[_-]([0-9]+)[_-]([0-9]+)[_-]([0-9]+)[_-]([0-9]+)/", basename( $this->GetFileName() ), $ver ) == 1 )
            {
                $iVersion = ( ( intval( $ver[ 1 ] ) & 0xFF ) << 24 ) |
                    ( ( intval( $ver[ 2 ] ) & 0xFF ) << 16 ) |
                    ( ( intval( $ver[ 3 ] ) & 0xFF ) << 8 ) |
                    ( ( intval( $ver[ 4 ] ) & 0xFF ) << 0 );
            }
        }
        if( $iVersion == 0 )
        {
            $iVersion = $this->m_iVersion;
        }
        $sResult = sprintf( "%d%s%d%s%d%s%d", ( $iVersion & 0xFF000000 ) >> 24, $sSep, ( $iVersion & 0x00FF0000 ) >> 16, $sSep,
            ( $iVersion & 0x0000FF00 ) >> 8, $sSep, ( $iVersion & 0x000000FF ) >> 0 );
        return $sResult;
    }

    public function Match( $oFwItem )
    {
        return ( $this->GetDevice() == $oFwItem->GetDevice() ) && ( $this->GetVersion() == $oFwItem->GetVersion() );
    }

    public function GetDevice()
    {
        return $this->m_sDevice;
    }

    public function GetVersion()
    {
        return $this->m_iVersion;
    }
}
