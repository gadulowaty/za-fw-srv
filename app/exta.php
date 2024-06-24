<?php

require_once( "config/exta.conf" );
require_once( "exta_firmware.php" );
require_once( "exta_changelog.php" );


require_once( "exta_cache.php" );
require_once( "exta_const.php" );
require_once( "exta_context.php" );
require_once( "exta_opts.php" );
require_once( "exta_tool.php" );
require_once( "exta_fw_repo.php" );
require_once( "exta_tab.php" );

class ExtaApp
{
    /**
     *
     * @var ExtaApp
     */
    private static ExtaApp $_app;
    /**
     * @var ExtaContext[]
     */
    private static array $_context_registry = [];
    private ExtaOpts $_opts;
    private ExtaCache $_cache;

    private function __construct( $options )
    {
        $this->_opts = new ExtaOpts( $options );
        $this->_repo = new ExtaFwRepo( $options );
        $cache_dir = $this->_opts->ReadStr( "cache_dir", ExtaTool::PathCombine( "cache" ) );
        $this->_cache = new ExtaCache( $cache_dir, $this->_opts->ReadInt( "cache_max", 3600 ) );
    }

    public static function RegisterContext( string $context_name, ExtaContextFactory $contextFactory )
    {
        self::$_context_registry[ $context_name ] = [ "factory" => $contextFactory ];
    }

    public static function GetApp( $repo ) : ExtaApp
    {
        global $_EXTA_CONF;
        if( !isset( self::$_app ) )
        {
            self::$_app = new ExtaApp( array_merge( $_EXTA_CONF, [ "repo" => self::GetDefaultRepo( $_EXTA_CONF, $repo ) ] ) );
        }
        return self::$_app;
    }

    private static function GetDefaultRepo( array $options, string $repo_def ) : string
    {
        $hostname = $_SERVER[ "REMOTE_ADDR" ] ?? gethostbyaddr( gethostbyname( gethostname() ) );
        foreach( ExtaTool::VarReadArr( $options, "repo_default" ) as $ipaddr => $repo_name )
        {
            if( strcasecmp( $hostname, $ipaddr ) == 0 )
            {
                return $repo_name;
            }
        }
        return $repo_def;
    }

    public function Welcome( string $context = "" ) : void
    {
        $hostname = $_SERVER[ "REMOTE_ADDR" ] ?? gethostbyaddr( gethostbyname( gethostname() ) );
        if( $context != "" )
        {
            $context .= " ";
        }
        if( ExtaTool::IsConsole() )
        {
            ExtaTool::OutOut( "Hello $hostname, this is Exta Life " . $context . "Server @%s repository\n", $this->_repo->GetName() );
        }
        else
        {
            ExtaTool::OutOut( "<pre><h1 style='text-align: center'>Hello $hostname, this is Exta Life " . $context . "Server <b>@%s</b> repository</h1>", $this->_repo->GetName() );
        }
    }

    public function IsConsole() : bool
    {
        return php_sapi_name() == "cli";
    }

    public function GetResource( string $sUrl, array $aHttpOpts = [], $sContentType = "application/json" ) : string|bool
    {
        return $this->_cache->GetResource( $sUrl, $aHttpOpts, $sContentType );
    }

    public function GetCache() : ExtaCache
    {
        return $this->_cache;
    }

    public function GetRepo() : ExtaFwRepo
    {
        return $this->_repo;
    }

    public function ServiceDispatch( string $path, array $options ) : bool
    {
        $bResult = false;
        if( ( $ctx = $this->CreateContext( $path, $options ) ) !== false )
        {
            $bResult = $ctx->Dispatch();
            if( !$bResult )
            {
                $this->ServiceError( 404, "Not Found" );
            }
        }
        else
        {
            $this->ServiceError( 500, "Internal Server Error" );
        }
        return $bResult;
    }

    private function CreateContext( string $path, array $options ) : ExtaContext|bool
    {
        if( array_key_exists( $path, self::$_context_registry ) )
        {
            return self::$_context_registry[ $path ][ "factory" ]->create( $this, $this->_opts->Merge( $options ) );
        }
        return false;
    }

    public function ServiceError( int $iCode, string $sMessage ) : void
    {
        if( $this->IsConsole() )
        {
            printf( "Error %d: %s\n", $iCode, $sMessage );
        }
        else
        {
            header( sprintf( "HTTP/1.1 %3d %s", $iCode, $sMessage ) );
        }
        die();
    }
}

