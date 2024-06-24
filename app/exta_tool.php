<?php


class ExtaTool
{
    private static function PrintBufferLine( $iBase, $aValues, $iOffset, $iLength, $iTotal, $iBytes = 1 ) : string
    {
        $sResult = "| " . ( $iBase < 0 ? "  Offset  " : sprintf( "0x%08x", $iBase * $iBytes ) ) . " |";
        $fmtStr = " %0" . strval( $iBytes * 2 ) . "x";
        $sAscii = " ";
        $iOrder = 1;
        $aSpaces = [ 8, 4, 2, 0, 0, 0, 0, 0 ];
        $iSpace = $aSpaces[ $iBytes - 1 ];
        for( $index = $iOffset; $index < $iOffset + $iLength; $index++ )
        {
            $sResult .= sprintf( $fmtStr, $aValues[ $index ] );
            if( ( $iSpace > 0 ) && ( $iOrder > 0 ) && ( $iOrder != $iTotal ) && ( $iOrder % $iSpace == 0 ) )
            {
                $sResult .= " ";
            }
            if( $iBase >= 0 )
            {
                $iValue = $aValues[ $index ];
                $iMask = 0xFF;
                for( $iByte = $iBytes - 1; $iByte >= 0; $iByte-- )
                {
                    $iShift = ( $iBytes - $iByte - 1 ) * 8;
                    $iChar = ( ( $iValue & ( 0xFF << $iShift ) ) >> $iShift ) & 0xFF;
                    $sAscii .= ( $iChar >= 32 ? chr( $iChar ) : "." );
                }
            }
            $iOrder += 1;
        }
        if( $iLength < $iTotal )
        {
            $sResult .= str_repeat( " ", ( $iTotal - $iLength ) * ( ( $iBytes * 2 ) + 1 ) + ( $iLength < 8 ? 1 : 0 ) );
            $sAscii .= str_repeat( " ", ( $iTotal - $iLength ) * $iBytes );
        }
        $sResult .= " |";
        if( $iBase >= 0 )
        {
            $sResult .= $sAscii;
        }
        else
        {
            $sResult .= str_pad( "ASCII", ( $iTotal * $iBytes ) + 1, " ", STR_PAD_BOTH );
        }
        $sResult .= " |";
        return $sResult;
    }

    private static function PrintBuffer( array $aBuffer, int $iBytes, int $iMaxPerLine = 16 ) : array
    {
        $aHeader = ( $iMaxPerLine * $iBytes ) - 1 > $iBytes ? range( 0, ( $iMaxPerLine * $iBytes ) - 1, $iBytes ) : [ 0 ];
        $aOutput = [];
        $aOutput[] = "";

        $aOutput[] = self::PrintBufferLine( -1, $aHeader, 0, $iMaxPerLine, $iMaxPerLine, $iBytes );
        $aOutput[] = str_repeat( "-", strlen( $aOutput[ 1 ] ) );
        $aOutput[ 0 ] = $aOutput[ 2 ];

        $iOffset = 0;
        $iTotalSize = count( $aBuffer );
        while( $iOffset < $iTotalSize )
        {
            $iCurLine = min( $iTotalSize - $iOffset, $iMaxPerLine );
            $aOutput[] = self::PrintBufferLine( $iOffset, $aBuffer, $iOffset, $iCurLine, $iMaxPerLine, $iBytes );
            $iOffset += $iCurLine;
        }
        $aOutput[] = $aOutput[ 0 ];
        return $aOutput;
    }

    public static function PrintAsBytes( array $aBuffer, $iMaxPerLine = 16 ) : string
    {
        return join( "\n", self::PrintBuffer( $aBuffer, 1, $iMaxPerLine ) );
    }

    public static function PrintAsWords( array $aBuffer, $iMaxPerLine = 8 ) : string
    {
        return join( "\n", self::PrintBuffer( $aBuffer, 2, $iMaxPerLine ) );
    }

    public static function PrintAsDWords( array $aBuffer, $iMaxPerLine = 4 ) : string
    {
        return join( "\n", self::PrintBuffer( $aBuffer, 4, $iMaxPerLine ) );
    }

    public static function PrintAsQWords( array $aBuffer, $iMaxPerLine = 2 ) : string
    {
        return join( "\n", self::PrintBuffer( $aBuffer, 8, $iMaxPerLine ) );
    }

    public static function BufferToBytes( string $sBuffer ) : array
    {
        return array_values( unpack( "C*", $sBuffer ) );
    }

    public static function BufferToWords( string $sBuffer ) : array
    {
        return array_values( unpack( "S*", $sBuffer ) );
    }

    public static function BufferToDWords( string $sBuffer ) : array
    {
        return array_values( unpack( "L*", $sBuffer ) );
    }

    public static function BufferToQWords( string $sBuffer ) : array
    {
        return array_values( unpack( "Q*", $sBuffer ) );
    }

    public static function VarReadInt( $aInput, $sValName, $xValDef = -1 )
    {
        if( self::VarExists( $aInput, $sValName ) )
        {
            $sValue = $aInput[ $sValName ];
            if( is_numeric( $sValue ) )
            {
                return intval( $sValue );
            }
        }
        return $xValDef;
    }

    public static function VarExists( $aInput, $sValName )
    {
        if( is_array( $aInput ) && array_key_exists( $sValName, $aInput ) )
        {
            return true;
        }
        return false;
    }

    public static function VarReadBool( array $aInput, string $sValName, bool $xValDef = false ) : bool
    {
        if( self::VarExists( $aInput, $sValName ) )
        {
            $sValue = $aInput[ $sValName ];
            return ( $sValue == $sValName ) || ( $sValue == "true" ) || ( $sValue == "y" ) || ( $sValue == "yes" ) || intval( $sValue ) != 0;
        }
        return $xValDef;
    }

    public static function VarReadStr( $aInput, $sValName, $xValDef = "" )
    {
        if( self::VarExists( $aInput, $sValName ) )
        {
            return $aInput[ $sValName ];
        }
        return $xValDef;
    }

    public static function ConDump( mixed $xVar ) : void
    {
        if( self::IsConsole() )
        {
            self::OutDump( $xVar );
        }
    }

    public static function ConBuffer( mixed $xVar ) : void
    {
        if( self::IsConsole() )
        {
            self::OutBuffer( $xVar );
        }
    }

    public static function ConOut( string $fmt, ...$args ) : void
    {
        if( self::IsConsole() )
        {
            self::OutOut( $fmt, ...$args );
        }
    }

    public static function OutBuffer( string $sBuffer ) : void
    {
        self::OutOut( self::PrintAsBytes( self::BufferToBytes( $sBuffer ) ) );
    }

    public static function OutDump( mixed $xVar ) : void
    {
        ob_start();
        var_dump( $xVar );
        $sOutput = ob_get_contents();
        ob_end_clean();
        self::OutOut( $sOutput );
    }

    public static function IsConsole() : bool
    {
        return ( php_sapi_name() == "cli" );
    }

    public static function OutOut( string $fmt, ...$args ) : void
    {
        if( count( $args ) > 0 )
        {
            $sOutput = sprintf( $fmt, ...$args );
        }
        else
        {
            $sOutput = $fmt;
        }
        if( !self::IsConsole() )
        {
            $sOutput = "<pre>" . str_replace( "\n", "<br />", $sOutput ) . "</pre>";
        }
        echo $sOutput;
    }

    public static function Crc32Calc( $initValue, $value )
    {
        $result = $initValue ^ $value;
        for( $binIndex = 0; $binIndex < 4 * 8; $binIndex++ )
        {
            if( ( $result & 0x80000000 ) != 0 )
            {
                $result = ( $result << 1 ) ^ CRC32_POLYNOMIAL;
            }
            else
            {
                $result = $result << 1;
            }
        }
        return $result;
    }

    public static function PathAbsolute( string $path ) : bool
    {
        $path = trim( $path );
        return ( ( strlen( $path ) > 0 ) && ( $path[ 0 ] == DIRECTORY_SEPARATOR ) );
    }

    public static function PathCombine( string $one, string $other = "", bool $normalize = true ) : string
    {
        $one = trim( $one );
        $other = trim( $other );
        if( $normalize )
        {
            if( DIRECTORY_SEPARATOR != '/' )
            {
                $one = str_replace( '/', DIRECTORY_SEPARATOR, $one );
                $other = str_replace( '/', DIRECTORY_SEPARATOR, $other );
            }
            if( DIRECTORY_SEPARATOR != '\\' )
            {
                $one = str_replace( '\\', DIRECTORY_SEPARATOR, $one );
                $other = str_replace( '\\', DIRECTORY_SEPARATOR, $other );
            }
        }
        if( ( strlen( $one ) > 0 ) && ( $one[ 0 ] != DIRECTORY_SEPARATOR ) )
        {
            $one = self::PathCombine( self::PathRoot(), $one );
        }
        elseif( empty( $one ) )
        {
            $one = self::PathRoot();
        }

        # remove leading/trailing dir separators
        if( !empty( $one ) && substr( $one, -1 ) == DIRECTORY_SEPARATOR )
        {
            $one = substr( $one, 0, -1 );
        }
        if( !empty( $other ) && substr( $other, 0, 1 ) == DIRECTORY_SEPARATOR )
        {
            $other = substr( $other, 1 );
        }

        # return combined path
        if( empty( $one ) )
        {
            return $other;
        }
        elseif( empty( $other ) )
        {
            return $one;
        }
        else
        {
            return $one . DIRECTORY_SEPARATOR . $other;
        }
    }

    public static function PathRoot() : string
    {
        return dirname( __FILE__, 2 );
    }

    public static function VarReadArr( array $aInput, string $sValName, array $aValDef = [] ) : array
    {
        if( self::VarExists( $aInput, $sValName ) )
        {
            $xValue = $aInput[ $sValName ];
            if( is_array( $xValue ) )
            {
                $aResult = $xValue;
            }
            else
            {
                $aResult = preg_split( '/[,;.]/', $xValue, -1, PREG_SPLIT_NO_EMPTY );
                if( $aResult === false )
                {
                    if( strcasecmp( $sValName, $xValue ) == 0 )
                    {
                        $aResult = $aValDef;
                    }
                    else
                    {
                        $aResult = [ $xValue ];
                    }
                }
            }
            return $aResult;
        }
        return $aValDef;
    }

    public static function DumpString( string $string )
    {
        return ExtaTool::PrintAsBytes( ExtaTool::BufferToBytes( $string ) );
    }

    public static function DumpBytes( array $aBytes ) : string
    {
        return ExtaTool::PrintAsBytes( $aBytes );
    }
}
