<?php

require_once( "exta.php" );
require_once( "exta_fw_client.php" );
require_once( "exta_context.php" );

class ExtaFirmwareFactory extends ExtaContextFactory
{
    protected function createContext( ExtaApp $app, array $options )
    {
        return new ExtaFirmware( $app, $options );
    }
}

class ExtaFirmware extends ExtaContext
{
    public function __construct( ExtaApp $app, array $options )
    {
        parent::__construct( $app, $options );
    }

    protected function DispatchHandler() : bool
    {
        if( $this->_opts->Exists( "test" ) )
        {
            return $this->HandleTest();
        }
        if( $this->_opts->Exists( "download" ) )
        {
            return $this->HandleDownload();
        }
        if( $this->_opts->Exists( "print" ) )
        {
            return $this->HandlePrint();
        }
        elseif( $this->_opts->Exists( "import" ) && $this->IsConsole() )
        {
            return $this->HandleImport();
        }
        elseif( $this->_opts->Exists( "list" ) )
        {
            return $this->HandleList();
        }
        elseif( $this->_opts->ReadStr( "file" ) != "" )
        {
            return $this->HandleFile();
        }
        elseif( $this->_opts->ReadStr( "name" ) != "" )
        {
            if( !$this->HandleNameChunk() )
            {
                return $this->HandleNameIndex();
            }
            return true;
        }
        return $this->HandlePrint();
    }

    private function HandleTest() : bool
    {
        $string = 'ExtaTool::OutOut( $aTable->GetILine( [ ++$i, $oFwItem->GetDevice(), $oFwItem->GetSize(), $oFwItem->GetVersionStr(), $name ] ) )';
        // $bytes = ExtaTool::DataAsBytes($string);
        $string = "Exta Life Firmware Server @%s\n";

        $this->_app->Welcome( "Firmware" );
        ExtaTool::OutOut( ExtaTool::DumpString( $string ) );

        return true;
    }

    private function HandleDownload() : bool
    {
        $sServerAddr = $this->_opts->ReadStr( "server", EXTA_FW_SERVER_IP_REAL );

        $repo = $this->GetRepo();

        ExtaTool::OutOut( "Download firmwares from %s...\n", $sServerAddr );
        ExtaTool::OutOut( "Repository used: '%s'\n", $repo->GetPath() );
        $oFwSrvClient = $this->CreateClient( $sServerAddr, $this->GetHttpOpts() );

        if( ( $aFwList = $oFwSrvClient->GetList() ) !== false )
        {
            foreach( $aFwList as $aFwEntry )
            {
                $bResult = $oFwSrvClient->Download( $aFwEntry, $repo->GetPath() );
                ExtaTool::OutOut( "Fetched '%s', version=%s, size=%d byte(s) with result %s\n", $aFwEntry[ "device" ], $aFwEntry[ "version" ][ "str" ], $aFwEntry[ "size" ], ( $bResult ? "SUCCESS" : "ERROR" ) );
            }
            ExtaTool::OutOut( "Found %d item(s)\n", count( $aFwList ) );
            $this->GetRepo()->IndexRebuild();
        }
        else
        {
            ExtaTool::ConOut( "ERROR: Failed to download firmwares\n" );
        }
        return true;
    }

    private function GetRepo() : ExtaFwRepo
    {
        if( !isset( $this->_repo ) )
        {
            $app_repo = $this->_app->GetRepo();

            $repo_path = ExtaTool::PathCombine( $this->_opts->ReadStr( "repo_base", "repo" ), $this->_opts->ReadStr( "repo", $app_repo->GetName() ) );

            $repo = ( empty( $repo_path ) || ( $app_repo->GetPath() == $repo_path ) ) ? $app_repo : new ExtaFwRepo( $this->_opts->GetAll(), $repo_path );

            ExtaTool::ConOut( "Using repo '%s' ('%s') \n", $repo == $app_repo ? "SYSTEM" : "LOCAL", $repo->GetPath() );
            $this->_repo = $repo;
        }
        return $this->_repo;
    }

    private function CreateClient( string $sServerAddr, array $aHttpOpts ) : ExtaFwSrvClient
    {
        return new ExtaFwSrvClient( $sServerAddr, $aHttpOpts, $this->_opts->GetAll(), $this->_app->GetCache() );
    }

    private function GetHttpOpts() : array
    {
        $aResult = EXTA_FW_SRV_AUTH;
        $aResult[ CURLOPT_USERNAME ] = $this->_opts->ReadStr( "zamel_user" );
        $aResult[ CURLOPT_PASSWORD ] = $this->_opts->ReadStr( "zamel_pass" );
        return $aResult;
    }

    private function HandlePrint() : bool
    {
        $aTable = new ExtaTab( [ [ 3, "r" ], [ 7, "c" ], [ 8, "r" ], [ 15, "r" ], [ 32, "l" ] ], "" );

        $repo = $this->GetRepo();

        $aFwList = $repo->GetAllByDevice( $this->_opts->ReadArr( "device" ) );
        $this->_app->Welcome( "Firmware" );
        $output = sprintf( "Repository in use '%s'\n", $repo->GetName() );
        $output .= $aTable->GetHLine( EXTAB_LINE_TOP ) . "\n";
        $output .= $aTable->GetHLine( [ "Id", "Device", "Size", "Version", "Source" ] ) . "\n";
        $output .= $aTable->GetHLine( EXTAB_LINE_MID ) . "\n";
        $i = 0;
        foreach( $aFwList as $oFwItem )
        {
            $name = basename( $oFwItem->GetFileName() );
            if( !ExtaTool::IsConsole() )
            {
                $name = [ $name, "<a href='" . sprintf( "/firmware/?repo=%s&file=%s", $repo->GetName(), $name ) . "'>$name</a>" ];
            }
            $output .= $aTable->GetILine( [ ++$i, $oFwItem->GetDevice(), $oFwItem->GetSize(), $oFwItem->GetVersionStr(), $name ] ) . "\n";
        }
        $output .= $aTable->GetHLine( EXTAB_LINE_BOT ) . "\n";

        ExtaTool::OutOut( $output );

        return true;
    }

    private function IsConsole() : bool
    {
        return $this->_app->IsConsole();
    }

    private function HandleImport() : bool
    {
        $aFwList = ExtaFwRepo::ScanDir( $this->_opts->ReadStr( "path" ), $this->_opts->ReadBool( "recursive" ) );
        foreach( $aFwList as $oFwItem )
        {
            if( $this->GetRepo()->Import( $oFwItem ) === true )
            {
                echo sprintf( "Imported: %s (%s)\n", $oFwItem->GetDevice(), $oFwItem->GetVersionStr() );
            }
            else
            {
                echo sprintf( "Skipped: %s (%s)\n", $oFwItem->GetDevice(), $oFwItem->GetVersionStr() );
            }
        }
        return true;
    }

    private function HandleList() : bool
    {
        $aRepoList = $this->GetRepo()->GetAllByDeviceGrouped( $this->_opts->ReadArr( "device" ) );

        /** @var ExtaFwItem[] $aFwItems */
        $items = [];
        foreach( $aRepoList as $sDevice => $aFwItems )
        {
            $index = $this->GetRepo()->GetHistoryIndex( $aFwItems );
            if( $index >= 0 )
            {
                $oFwItem = $aFwItems[ $index ];
                $items[] = basename( $oFwItem->GetFileName() );
            }
        }
        $list = [ "name" => $items ];
        $output = json_encode( $list );
        header( "Content-type: application/json", true );
        echo $output;
        return true;
    }

    private function HandleFile()
    {
        $repo = $this->GetRepo();
        $file = $this->_opts->ReadStr( "file" );
        if( !empty( $file ) )
        {
            $filename = ExtaTool::PathCombine( $repo->GetPath(), $file );
            if( file_exists( $filename ) )
            {
                $data = file_get_contents( $filename );
                if( $this->_opts->Exists("text"))
                {
                    ExtaTool::OutBuffer( $data );
                }
                else
                {
                    header("Content-Type: application/octet-stream");
                    header("Content-Disposition: attachment; filename=\"$file\"");
                    echo $data;
                }
                return true;
            }
        }
        return false;
    }

    private function HandleNameChunk() : bool
    {
        $iStart = $this->_opts->ReadInt( "start" );
        $iSize = $this->_opts->ReadInt( "size" );
        if( ( $iStart < 0 ) || ( $iSize <= 0 ) )
        {
            return false;
        }

        $sSearchFor = $this->_opts->ReadArr( "name" );
        /** @var ExtaFwItem $oFwItem */
        if(
            ( ( $oFwItem = $this->GetRepo()->GetItemByDevice( $sSearchFor ) ) !== false ) &&
            ( ( $jsObject = $oFwItem->GetNamedChunk( $iStart, $iSize ) ) !== false ) )
        {
            header( "Content-type: application/json", true );
            echo json_encode( $jsObject );
            return true;
        }
        return false;
    }

    private function HandleNameIndex() : bool
    {
        $sSearchFor = $this->_opts->ReadArr( "name" );
        /** @var ExtaFwItem $oFwItem */
        if( ( $oFwItem = $this->GetRepo()->GetItemByDevice( $sSearchFor ) ) !== false )
        {
            header( "Content-type: application/json", true );
            echo json_encode( $oFwItem->GetNamedData() );
            return true;
        }
        return false;
    }
}

ExtaApp::RegisterContext( "firmware", new ExtaFirmwareFactory() );
