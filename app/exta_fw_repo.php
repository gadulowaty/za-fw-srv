<?php
require_once( "exta_opts.php" );
require_once( "exta_fw_item.php" );

const EXTA_FW_EFC_MAGIC1 = 0x00000030;
const EXTA_FW_EFC_MAGIC2 = 0x00000000;
const EXTA_FW_MOD_MAGIC = 0xBADC0FFE;
const EXTA_FW_INFO_SIZE = 12;
const HISTORY_BACK_IN_TIME = -3;

class ExtaFwRepoOpts
{
    private function __construct(object $oInit = null)
    {
        if( !is_null( $oInit ) )
        {
            $this->ver_by_name = $oInit->ver_by_name;
            if(property_exists($oInit, "history"))
            {
                $this->history = $oInit->history;
            }
        }
    }

    public bool $ver_by_name = false;
    public int $history = 0;

    public static function Create( $sDirName )
    {
        $sFileName = ExtaTool::PathCombine($sDirName, ".options" );
        $oInit = json_decode(@file_get_contents($sFileName));
        $oResult = new ExtaFwRepoOpts( $oInit );
        file_put_contents( $sFileName, json_encode( $oResult, JSON_PRETTY_PRINT ) );
        return $oResult;
    }
}

class ExtaFwRepo
{
    private $_path;
    private $m_aList;

    private ExtaOpts $_opts;
    private ExtaFwRepoOpts $_repo_opts;

    public function __construct( $options )
    {
        $this->_opts = new ExtaOpts( $options );

        $repo_base = $this->_opts->ReadStr( "repo_base", ExtaTool::PathCombine( "repo" ) );

        $repo_name = $this->_opts->ReadStr( "repo", EXTA_DEFAULT_REPO );

        $this->_path = ExtaTool::PathCombine( $repo_base, $repo_name );

        if( !is_dir( $this->_path ) )
        {
            @mkdir( $this->_path, 0777, true );
        }

        $this->_repo_opts = ExtaFwRepoOpts::Create($this->_path);
        if( ( $aFwList = $this->Unserialize() ) !== false )
        {
            $bAnyFixed = false;
            foreach($aFwList as $oFwItem )
            {
                if( $oFwItem->FixName() )
                {
                    $bAnyFixed = true;
                }
            }
            if( !$bAnyFixed )
            {
                $this->m_aList = $aFwList;
            }
            else
            {
                @unlink($this->IndexGetFileName());
            }
        }
    }

    private function Unserialize() : array|bool
    {
        $sIndexFile = $this->IndexGetFileName();
        $sDirName = dirname( $sIndexFile );
        if( ( $aIndexData = $this->IndexRead( $sIndexFile ) ) !== false )
        {
            $aFwList = [];
            foreach( $aIndexData as $aIndexEntry )
            {
                if( ( $oFwItem = ExtaFwItem::FromIndexEntry( $sDirName, $aIndexEntry ) ) !== false )
                {
                    $aFwList[] = $oFwItem;
                }
            }
            return $aFwList;
        }
        return false;
    }

    public function IndexGetFileName()
    {
        return ExtaTool::PathCombine( $this->_path, ".index.json" );
    }

    private function IndexRead( $sIndexFile )
    {
        if( ( $sIndexContent = @file_get_contents( $sIndexFile ) ) !== false )
        {
            $aResult = json_decode( $sIndexContent, true );
            return $aResult;
        }
        return false;
    }

    public static function ScanDir( $sPath, $lRecursive = false )
    {
        $aResult = false;
        if( ( $hDir = opendir( $sPath ) ) !== false )
        {
            $aDirs = array();
            $aResult = array();
            while( ( $sEntry = readdir( $hDir ) ) !== false )
            {
                if( substr( $sEntry, 0, 1 ) == "." )
                {
                    continue;
                }

                $sFullName = ExtaTool::PathCombine( $sPath, $sEntry );
                if( is_file( $sFullName ) )
                {
                    if( strcasecmp( pathinfo( $sFullName, PATHINFO_EXTENSION ), "bin" ) == 0 )
                    {
                        if( ( $oFwItem = ExtaFwItem::OpenEx( $sFullName ) ) !== false )
                        {
                            $aResult[] = $oFwItem;
                        }
                    }
                }
                else if( is_dir( $sFullName ) && $lRecursive )
                {
                    $aDirs[] = $sFullName;
                }
            }
            closedir( $hDir );

            for( $i = 0; $i < count( $aDirs ); $i++ )
            {
                $aSubResult = self::ScanDir( $aDirs[ $i ], $lRecursive );
                if( $aSubResult !== false )
                {
                    $aResult = array_merge( $aResult, $aSubResult );
                }
            }
        }
        return $aResult;
    }

    public function sorter( $itemA, $itemB )
    {
        return strcmp( $itemA->GetDevice(), $itemB->GetDevice() );
    }

    public function GetAllByDeviceGrouped( mixed $xDevice = [], bool $bVerDesc = true ) : array
    {
        $result = [];
        $list = $this->GetAllByDevice( $xDevice, $bVerDesc );
        /** @var ExtaFwItem $item */
        foreach( $list as $item )
        {
            $sDevice = $item->GetDevice();
            if( !array_key_exists( $sDevice, $result ) )
            {
                $result[ $item->GetDevice() ] = [];
            }
            array_push( $result[ $sDevice ], $item );
        }

        uksort( $result, function ( $x, $y ) {
            return strcasecmp( $x, $y );
        } );
        return $result;
    }

    public function GetHistoryIndex(array $aArray, ?int $iHistory = null) : int
    {
        if( !isset($iHistory))
        {
            $iHistory = $this->_opts->ReadInt("history", $this->_repo_opts->history);
        }
        return min( count( $aArray ) - 1, abs( $iHistory ) );
    }

    public function GetItemByDevice( mixed $xDevice = [], bool $bVerDesc = true ) : ExtaFwItem | bool
    {
        $aFwItems = $this->GetAllByDevice( $xDevice, $bVerDesc );
        $index = $this->GetHistoryIndex($aFwItems);
        if( $index < 0 )
        {
            return false;
        }
        return $aFwItems[ $index ];
    }

    public function GetAllByDevice( mixed $xDevice = [], bool $bVerDesc = true ) : array
    {
        $aList = [];
        /** @var ExtaFwItem $oFwItem */
        foreach( $this->GetList() as $oFwItem )
        {
            if( ( is_array( $xDevice ) && count( $xDevice ) == 0 ) || ( is_string( $xDevice ) && empty( $xDevice ) ) )
            {
                $aList[] = $oFwItem;
                continue;
            }
            if( is_array( $xDevice ) )
            {
                foreach( $xDevice as $sDevice )
                {
                    if( ( $sDevice == "" ) || ( strcasecmp( $oFwItem->GetDevice(), $sDevice ) == 0 ) )
                    {
                        $aList[] = $oFwItem;
                        break;
                    }
                }
            }
            elseif( is_string( $xDevice ) )
            {
                if( ( $xDevice == "" ) || ( strcasecmp( $oFwItem->GetDevice(), $xDevice ) == 0 ) )
                {
                    $aList[] = $oFwItem;
                }
            }
        }
        if( count( $aList ) > 1 )
        {
            $iDir = $bVerDesc ? 1 : -1;
            uasort( $aList, function ( ExtaFwItem $ia, ExtaFwItem $ib ) use ( $iDir ) {
                $iSortOrd = strcmp( $ia->GetDevice(), $ib->GetDevice());
                if($iSortOrd == 0 )
                {
                    $iSortOrd = ( ( $ib->GetVersion() & 0xFFFF00FF ) - ( $ia->GetVersion() & 0xFFFF00FF ) ) * $iDir;
                }
                return $iSortOrd;
            } );
            $temp = [];
            foreach($aList as $item)
            {
                $temp[] = $item;
            }
            $aList = $temp;
        }
        return $aList;
    }

    private function GetList( $lForce = false )
    {
        if( !isset( $this->m_aList ) || $lForce )
        {
            $this->m_aList = $this->BuildList( $this->_path );
            $this->Serialize( $this->m_aList );
        }
        return $this->m_aList;
    }

    private function BuildList( $sSource )
    {
        $aResult = array();
        if( ( $hDir = @opendir( $sSource ) ) !== false )
        {
            while( ( $sEntry = readdir( $hDir ) ) !== false )
            {
                $sFullName = ExtaTool::PathCombine( $sSource, $sEntry );
                if( ( $fwItem = ExtaFwItem::Open( $sFullName ) ) !== false )
                {
                    $aResult[] = $fwItem;
                }
            }
            closedir( $hDir );
        }
        return $aResult;
    }

    private function Serialize( array $aFwList ) : void
    {
        $allData = [];
        /** @var ExtaFwItem $fwItem */
        foreach( $aFwList as $fwItem )
            $allData[] = $fwItem->GetSerializationData();
        $data = json_encode( $allData, JSON_PRETTY_PRINT );
        @file_put_contents( $this->IndexGetFileName(), $data );
    }

    public function GetAll()
    {
        $aList = $this->GetList();
        uasort( $aList, array( $this, 'sorter' ) );
        return $aList;
    }

    public function Get( $sDevice ) {}

    public function Import( ExtaFwItem $oFwItemImport ) : bool|ExtaFwItem
    {
        if( !$this->Contains( $oFwItemImport ) )
        {
            $sNewFileName = ExtaTool::PathCombine( $this->GetPath(), sprintf( "%s_%s.bin", $oFwItemImport->GetDevice(), $oFwItemImport->GetVersionStr( "_" ) ) );
            if( copy( $oFwItemImport->GetFileName(), $sNewFileName ) === true )
            {
                if( ( $oFwItem = ExtaFwItem::OpenEx( $sNewFileName ) ) !== false )
                {
                    $this->m_aList[] = $oFwItem;
                    $this->Serialize( $this->m_aList );
                    return $oFwItem;
                }
                else
                {
                    unlink( $sNewFileName );
                }
            }
        }
        return false;
    }

    public function Contains( ExtaFwItem $oFwItem ) : bool
    {
        return ( $this->IndexOf( $oFwItem ) >= 0 );
    }

    public function IndexOf( ExtaFwItem $oFwItem ) : int
    {
        $iIndex = -1;
        foreach( $this->GetList() as $oFwItemList )
        {
            $iIndex++;
            if( $oFwItemList->Match( $oFwItem ) )
            {
                return $iIndex;
            }
        }
        return -1;
    }

    public function GetPath()
    {
        return $this->_path;
    }

    public function GetName()
    {
        return basename( $this->_path );
    }

    public function IndexRebuild() : bool
    {
        $this->GetList(true);
        return true;
    }
}

?>