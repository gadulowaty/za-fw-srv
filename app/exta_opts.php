<?php

require_once( "exta_tool.php" );

class ExtaOpts
{
    private array $_options;

    public function __construct( $aOptions )
    {
        $this->_options = $aOptions;
    }

    public function Exists( string $sValName ) : bool
    {
        return ExtaTool::VarExists( $this->_options, $sValName );
    }

    public function GetAll() : array
    {
        return $this->_options;
    }

    public function Merge( array $options ) : array
    {
        return array_merge($this->_options, $options );
    }

    public function ReadBool( string $sValName, bool $bValDef = false ) : bool
    {
        return ExtaTool::VarReadBool( $this->_options, $sValName, $bValDef );
    }

    public function ReadInt( string $sValName, int $iValDef = -1 ) : int
    {
        return ExtaTool::VarReadInt( $this->_options, $sValName, $iValDef );
    }

    public function ReadStr( string $sValName, string $sValDef = "" ) : string
    {
        return ExtaTool::VarReadStr( $this->_options, $sValName, $sValDef );
    }

    public function ReadArr( string $sValName, array $aValDef = [] ) : array
    {
        return ExtaTool::VarReadArr( $this->_options, $sValName, $aValDef );
    }

}
