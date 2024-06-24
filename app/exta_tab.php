<?php

define( "EXTAB_ALIGN_LEFT", 0 );
define( "EXTAB_ALIGN_CENTER", 1 );
define( "EXTAB_ALIGN_RIGHT", 2 );

define( "EXTAB_LINE_TOP", 0 );
define( "EXTAB_LINE_MID", 1 );
define( "EXTAB_LINE_BOT", 2 );

define( "HTL", 10 );
define( "HTS", 11 );
define( "HTR", 12 );
define( "HTC", 13 );

define( "HML", 20 );
define( "HMS", 21 );
define( "HMR", 22 );
define( "HMC", 23 );

define( "HBL", 30 );
define( "HBS", 31 );
define( "HBR", 32 );
define( "HBC", 33 );

define( "L_L", 40 );
define( "L_S", 41 );
define( "L_R", 42 );

define( "TFS", 99 );

class ExtaTab
{
	private $aChar;
	private $aCols;

	private function colAlign( $iCol, $xAlign )
	{
		if( is_string( $xAlign ) )
		{
			$cAlign = substr( $xAlign, 0, 1 );
			switch( $cAlign )
			{
				case "l": return EXTAB_ALIGN_LEFT;
				case "c": return EXTAB_ALIGN_CENTER;
				case "r": return EXTAB_ALIGN_RIGHT;
			}
			die( sprintf( "aCol[ %d ]: Invalid column align '%s'", $iCol, $xAlign ) );
		}
		else if( is_numeric( $xAlign ) )
		{
			$xAlign = intval( $xAlign );
			if( ( $xAlign >= EXTAB_ALIGN_LEFT ) && ( $xAlign <= EXTAB_ALIGN_RIGHT ) )
			{
				return $xAlign;
			}
			die( sprintf( "aCol[ %d ]: column aling out-of-bounds (%d)", $iCol, $xAlign ) );
		}
		die( sprintf( "aCol[ %d ]: column align should be string or integer", $iCol ) );
	}

	private function strAlign( $sValue, $iWidth, $iAlign )
	{
		switch( $iAlign )
		{
			case EXTAB_ALIGN_LEFT:
                if(is_array($sValue))
                {
                    return str_pad( $sValue[1], max( $iWidth + (strlen($sValue[1])-strlen($sValue[0])), strlen( $sValue[0] ) ), " ", STR_PAD_RIGHT );
                }
				return str_pad( $sValue, max( $iWidth, strlen( $sValue ) ), " ", STR_PAD_RIGHT );

			case EXTAB_ALIGN_CENTER:
				return str_pad( $sValue, max( $iWidth, strlen( $sValue ) ), " ", STR_PAD_BOTH );

			case EXTAB_ALIGN_RIGHT:
				return str_pad( $sValue, max( $iWidth, strlen( $sValue ) ), " ", STR_PAD_LEFT );
		}
		return $sValue;
	}

	private function colsAdd( $iSize, $xAlign )
	{
		$iCol = count( $this->aCols );
		if( !is_numeric( $iSize ) )
		{
			die( sprintf( "aCol[%d]: size should be numeric value", $iCol ) );
		}
		$this->aCols[] = [ "width" => intval( abs( $iSize ) ), "align" => $this->colAlign( $iCol, $xAlign ) ];
	}

	private function colsInit( $aCols )
	{
		$this->aCols = array();
		
		if( !is_array( $aCols ) )
		{
			die( "aCols should be array" );
		}
		
		for( $iCol = 0; $iCol < count( $aCols ); $iCol++ )
		{
			$aCol = $aCols[ $iCol ];
			if( is_numeric( $aCol ) )
			{
				if( $aCol > 0 )
				{
					$this->colsAdd( abs( $aCol ), EXTAB_ALIGN_LEFT );
				}
				else if( $aCol < 0 )
				{
					$this->colsAdd( abs( $aCol ), EXTAB_ALIGN_RIGHT );
				}
				else
				{
					die( sprintf( "aCol[%d]: cannot be 0", $iCol ) );
				}
			}
			else if( is_array( $aCol ) && ( count( $aCol ) > 1 ) )
			{
				$this->colsAdd( $aCol[ 0 ], $aCol[ 1 ] );
			}
			else
			{
				die( sprintf( "aCol[%d]: should be numeric value or array of min 2 elements", $iCol ) );
			}
		}
	}

	public function __construct( $aCols, $sChars )
	{
		$this->colsInit( $aCols );
		$this->aChar = [ 
			TFS => " ",

			HTL => "+",
			HTS => "+",
			HTR => "+",
			HTC => "-",

			HML => "+",
			HMS => "+",
			HMR => "+",
			HMC => "-",

			HBL => "+",
			HBS => "+",
			HBR => "+",
			HBC => "-",

			L_L => "|",
			L_S => "|",
			L_R => "|",
		];

	}

	public function GetHLine( $aNames )
	{
		$sResult = "";
		if( is_array( $aNames ) )
		{
			$iMaxCol = count( $this->aCols ) - 1;
			for( $iCol = 0; $iCol <= $iMaxCol; $iCol++ )
			{
				$aCol = $this->aCols[ $iCol ];
				if( $sResult == "" )
					$sResult .= $this->aChar[ L_L ];
				$sResult .= $this->aChar[ TFS ] . $this->strAlign( $aNames[ $iCol ], $aCol[ "width" ], EXTAB_ALIGN_CENTER ) . $this->aChar[ TFS ] . ( $iCol != $iMaxCol ? $this->aChar[ L_S ] : $this->aChar[ L_R ] );
			}
			return $sResult;
		}
		else if( is_int( $aNames ) )
		{
			$iMaxCol = count( $this->aCols ) - 1;
			for( $iCol = 0; $iCol <= $iMaxCol; $iCol++ )
			{
				$aCol = $this->aCols[ $iCol ];
				switch( $aNames )
				{
					case EXTAB_LINE_TOP:
						if( $sResult == "" )
							$sResult .= $this->aChar[ HTL ];
						$sResult .= str_repeat( $this->aChar[ HTC ], $aCol[ "width" ] + 2 * strlen( $this->aChar[ TFS ] ) ) . ( $iCol != $iMaxCol ? $this->aChar[ HTS ] : $this->aChar[ HTR ] );
						break;
					case EXTAB_LINE_MID:
						if( $sResult == "" )
							$sResult .= $this->aChar[ HML ];
						$sResult .= str_repeat( $this->aChar[ HMC ], $aCol[ "width" ] + 2 * strlen( $this->aChar[ TFS ] ) ) . ( $iCol != $iMaxCol ? $this->aChar[ HMS ] : $this->aChar[ HMR ] );
						break;
					case EXTAB_LINE_BOT:
						if( $sResult == "" )
							$sResult .= $this->aChar[ HBL ];
						$sResult .= str_repeat( $this->aChar[ HBC ], $aCol[ "width" ] + 2 * strlen( $this->aChar[ TFS ] ) ) . ( $iCol != $iMaxCol ? $this->aChar[ HBS ] : $this->aChar[ HBR ] );
						break;
					default:
						die( sprintf( "%s: aNames contains value out-of-bound (%d)", __FUNCTION__, $aNames ) );
				}
			}
			return $sResult;

		}
		die( sprintf( "%s: aNames should be array or integer", __FUNCTION__ ) );
	}

	public function GetILine( $aValues )
	{
		if( is_array( $aValues ) && ( count( $aValues ) ) >= count( $this->aCols ) )
		{
			$sResult = "";
			$iMaxCol = count( $this->aCols ) - 1;
			for( $iCol = 0; $iCol <= $iMaxCol; $iCol++ )
			{
				$aCol = $this->aCols[ $iCol ];
				if( $sResult == "" )
					$sResult .= $this->aChar[ L_L ];
				$sResult .= $this->aChar[ TFS ] . $this->strAlign( $aValues[ $iCol ], $aCol[ "width" ], $aCol[ "align" ] ) . $this->aChar[ TFS ] . ( $iCol != $iMaxCol ? $this->aChar[ L_S ] : $this->aChar[ L_R ] );
			}
			return $sResult;
		}

		die( sprintf( "%s: aValues should be array of string, at least %d long. %d items given.", __FUNCTION__, count( $this->aCols ), count( $aValues ) ) );
	}
}

?>