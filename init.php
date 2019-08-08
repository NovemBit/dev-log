<?php

/*
 * Register class autoload statement
 *
 * */
spl_autoload_register( function ( $className ) {

	if ( preg_match( '/^DevLog\\.*/', $className ) ) {

		$className = preg_replace( '/^DevLog/', 'src' );

		$className = str_replace( "\\", DIRECTORY_SEPARATOR, $className );

		include_once( __DIR__ . "/$className.php" );
	}
} );


if ( ! defined( "DEV_LOG" ) ) {
	define( "DEV_LOG", true );
}

if ( ! defined( "DEV_LOG_URL_PATH" ) ) {
	define( "DEV_LOG_URL_PATH", 'dlog' );
}

if ( ! defined( "DEV_LOG_PATH" ) ) {
	define( "DEV_LOG_PATH", dirname( __FILE__ ) );
}

if ( ! defined( "DEV_LOG_IP_ADDRESSES" ) ) {
	define( "DEV_LOG_IP_ADDRESSES", [ '*' ] );
}

if ( ! defined( "DEV_LOG_DEBUGGER" ) ) {
	define( "DEV_LOG_DEBUGGER", true );
}

if ( ! defined( "DEV_LOG_INLINE_DEBUGGER" ) ) {
	define( "DEV_LOG_INLINE_DEBUGGER", true );
}

if ( ! defined( "DEV_LOG_DB" ) ) {
	define( "DEV_LOG_DB", [
//		'pdo'=>'sqlite:'.dirname( __FILE__ ) . '/runtime/db/DevLog.db',
		'pdo'      => 'mysql:host=localhost;dbname=swanson',
		'username' => 'root',
		'password' => 'Novem9bit',
		'config'   => [
			\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false
		]
	] );
}

if ( DEV_LOG != false ) {
	DevLog\DevLog::register();
}