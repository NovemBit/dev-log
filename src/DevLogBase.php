<?php

namespace DevLog;
/*
 * Dev log
 * Simple and Powerful debugging tool
 * */

use DevLog\DataMapper\Models\Log;
use DevLog\DataMapper\Models\LogData;
use DevLog\DataMapper\Models\LogMessage;
use PDOException;


class DevLogBase {

	public static $scriptName = "DevLog";

	public static $scriptVersion = "1.0.4";

	private static $log;

	private static $_log_directory;

	private static $_logs_hash;

	private static $_track_directory;

	public static $messageTypes = [];

	public static $logTrackers = [];

	public static $hash_length = 12;

	public static $max_served_logs_count = 100;

	private static $db;

	/**
	 * Register logger
	 * To initialize logger should run this action
	 * @throws \Exception
	 */
	public static function register() {

		$ip_validation = DevLogHelper::ipAddressValidation( DEV_LOG_IP_ADDRESSES );

		if ( DEV_LOG_DEBUGGER == true ) {
			if ( $ip_validation ) {
				new DevLogController();
			}
		}

		/*
		 * Register request shutdown actions
		 * Then save log file as json
		 * And register inline debugger
		 * */
		register_shutdown_function(
			function () use ( $ip_validation ) {

				/*
				 * Register custom shutdown actions
				 * */
				static::registerShutDownActions();

				/*
				 * Save all logged data
				 * */
				\DevLog\DataMapper\Mappers\Log::save( self::$log );

				/**
				 * If DEV_LOG_INLINE_DEBUGGER is true and not ajax request
				 * Then load inline debugger on every request
				 * */
				if ( DEV_LOG_DEBUGGER == true && DEV_LOG_INLINE_DEBUGGER == true && $ip_validation && ! DevLogHelper::isXHRFromServer( $_SERVER ) ) {
					self::registerInlineDebugger();
				}
			}
		);

		static::registerStartActions();

	}

	/**
	 * @return \PDO
	 * @throws \Exception
	 */
	public static function getDb() {

		if ( ! isset( self::$db ) && defined( 'DEV_LOG_DB' ) ) {
			if ( ! isset( DEV_LOG_DB['pdo'] ) ) {
				throw new \Exception( 'DEV_LOG_DB["pdo"] not found' );
			}

			$pdo      = DEV_LOG_DB['pdo'];
			$username = DEV_LOG_DB['username'] ?? 'root';
			$password = DEV_LOG_DB['password'] ?? null;
			$config   = DEV_LOG_DB['config'] ?? [];

			self::$db = new \PDO( $pdo, $username, $password, $config );
		}

		return self::$db;
	}

	/**
	 * Render inline debugger
	 */
	private static function registerInlineDebugger() {
		$iframe = '<iframe style="border:none;" width="100%" height="38" src="/' . DEV_LOG_URL_PATH . '/inline/' . self::getLogHash() . '">Your browser does not support iframe.</iframe>';
		echo '<div id="DevLogInline" style="height:38px;z-index:999999999 !important; position: fixed;width:100%;bottom:0;left:0;">' . $iframe . '</div>';
	}

	/**
	 * Register start script
	 * @throws \Exception
	 */
	public static function registerStartActions() {

		self::setLog( new Log( null, self::getLogHash(), 'request' ) );

		set_error_handler( [ self::class, 'errorHandler' ] );

		self::getLog()->setName( self::getLogHash() );

		self::getLog()->getDataList()->addData( new LogData(null, 'start_time', microtime( true ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, '_server', ( isset( $_SERVER ) ? $_SERVER : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, '_session', ( isset( $_SESSION ) ? $_SESSION : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, '_env', ( isset( $_ENV ) ? $_ENV : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, '_get', ( isset( $_GET ) ? $_GET : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, '_post', ( isset( $_POST ) ? $_POST : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, '_cookie', ( isset( $_COOKIE ) ? $_COOKIE : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, '_files', ( isset( $_FILES ) ? $_FILES : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, 'request_headers', ( function_exists( 'getallheaders' ) ? getallheaders() : [] ) ) );
		self::getLog()->getDataList()->addData( new LogData(null, 'response_headers', headers_list() ) );
//		self::getLog()->getDataList()->addData( new LogData(null, 'php_info', self::getPhpInfo() ) );

	}

	/**
	 * @param $errorNumber
	 * @param $errorString
	 * @param $errorFile
	 * @param $errorLine
	 *
	 * @throws \Exception
	 */
	public static function errorHandler( $errorNumber, $errorString, $errorFile, $errorLine ) {

		$types = [
			E_ERROR             => "E_ERROR",
			E_WARNING           => "E_WARNING",
			E_PARSE             => "E_PARSE",
			E_NOTICE            => "E_NOTICE",
			E_CORE_ERROR        => "E_CORE_ERROR",
			E_CORE_WARNING      => "E_CORE_WARNING",
			E_COMPILE_ERROR     => "E_COMPILE_ERROR",
			E_COMPILE_WARNING   => "E_COMPILE_WARNING",
			E_USER_ERROR        => "E_USER_ERROR",
			E_USER_WARNING      => "E_USER_WARNING",
			E_USER_NOTICE       => "E_USER_NOTICE",
			E_STRICT            => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED        => "E_DEPRECATED",
			E_USER_DEPRECATED   => "E_USER_DEPRECATED",
			E_ALL               => "E_ALL",
		];
		$type  = $types[ $errorNumber ] ?? "UNDEFINED";
		self::log( "error",
			[
				"type"    => $type,
				'message' => $errorString,
				'file'    => $errorFile,
				'line'    => $errorLine
			],
			'PHP'
		);
	}

	/**
	 * Register shutdown script
	 * @throws \Exception
	 */
	public static function registerShutDownActions() {

		self::getLog()->getDataList()->addData( new LogData( null, 'memory_usage', memory_get_usage( true ) ) );
		self::getLog()->getDataList()->addData( new LogData( null, 'end_time', microtime( true ) ) );
		self::getLog()->getDataList()->addData( new LogData( null, 'status', http_response_code() ) );

	}


	private static function getPhpInfo() {

		ob_start();
		phpinfo();
		$php_info = ob_get_contents();
		ob_get_clean();

		return $php_info;
	}

	/**
	 * @return string
	 */
	public static function getLogDirectory() {
		if ( ! isset( self::$_log_directory ) ) {
			self::$_log_directory = dirname( __FILE__ ) . '/../runtime/logger';
		}

		return self::$_log_directory;
	}


	/**
	 * @return string
	 */
	public static function getTrackDirectory() {
		if ( ! isset( self::$_track_directory ) ) {
			self::$_track_directory = dirname( __FILE__ ) . '/../runtime/track';
		}

		return self::$_track_directory;
	}

	public static function getTrackers() {
		return [];
	}


	/**
	 * @return string
	 */
	public static function getLogHash() {
		if ( ! isset( self::$_logs_hash ) ) {
			self::$_logs_hash = substr( md5( uniqid( rand(), true ) ), 0, static::$hash_length );
		}

		return self::$_logs_hash;
	}

	/**
	 * @param $type
	 * @param $message
	 * @param string $category
	 *
	 * @return LogMessage
	 * @throws \Exception
	 */
	public static function log( $type, $message, $category = "default" ) {
		return self::getLog()->getMessageList()->addMessage( new LogMessage(
			null,
			$type,
			$message,
			$category,
			microtime( true )
		) );
	}


	/**
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return array
	 */
	public static function getLogs( $offset = 0, $limit = 100 ) {
		$files = glob( self::getLogDirectory() . '/*' );
		usort( $files, function ( $a, $b ) {
			return filemtime( $a ) < filemtime( $b );
		} );
		$files = array_slice( $files, $offset, $limit );


		$result = [];
		foreach ( $files as $file ) {
			$result[ basename( $file ) ] = json_decode( file_get_contents( $file ) );
		}

		return $result;
	}

	/**
	 * @return Log
	 */
	public static function getLog() {
		return self::$log;
	}

	/**
	 * @param Log $log
	 */
	public static function setLog( Log $log ) {
		self::$log = $log;
	}


}