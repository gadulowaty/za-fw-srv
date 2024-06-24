<?php


abstract class ExtaContextFactory
{
    abstract protected function createContext( ExtaApp $app, array $options );

    public function create(ExtaApp $app, array $options ) : ExtaContext
    {
        return $this->createContext($app, $options);
    }
}

abstract class ExtaContext
{
    protected ExtaApp $_app;
    protected ExtaOpts $_opts;

    abstract protected function DispatchHandler() : bool;

    public function __construct( ExtaApp $app, array $options )
    {
        $this->_app = $app;
        $this->_opts = new ExtaOpts( $options );
    }

    public function Dispatch() : bool
    {
        return $this->DispatchHandler();
    }

    protected function GetResource( string $sUrl, array $aHttpOpts = [], $sContentType = "application/json" )
    {
        return $this->_app->GetResource( $sUrl, $aHttpOpts, $sContentType );
    }
}
