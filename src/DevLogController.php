<?php

namespace DevLog;

use DevLog\DataMapper\Mappers\Log;

class DevLogController {


	public $layout = null;

	public $viewsDirectory = null;


	/**
	 * DevLogController constructor.
	 *
	 * @throws \Exception
	 */
	public function __construct() {

		if ( $this->viewsDirectory == null ) {
			$this->viewsDirectory = dirname( __FILE__ ) . '/../views';
		}

		if ( $this->layout == null ) {
			$this->layout = $this->viewsDirectory . '/layout.php';
		} else {
			$this->layout = $this->layout . '.php';
		}


		return $this->registerRoutes();
	}


	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function registerRoutes() {

		$route = strtok( isset( $_SERVER["REQUEST_URI"] ) ? $_SERVER["REQUEST_URI"] : '', '?' );

		if ( trim( $route, '/' ) == DEV_LOG_URL_PATH ) {

			$this->actionDefault();

			die();
		}

		if ( preg_match( '/^\/' . DEV_LOG_URL_PATH . '\/view\/(?<name>.*)?$/', $route, $matches ) ) {

			$this->actionView( $matches['name'] );

			die();
		}

		if ( preg_match( '/^\/' . DEV_LOG_URL_PATH . '\/inline\/(?<name>.*)?$/', $route, $matches ) ) {

			$this->actionInline( $matches['name'] );

			die();
		}

		if ( trim( $route, '/' ) == DEV_LOG_URL_PATH . '/track' ) {

			$this->actionTrack();

			die();
		}

		if ( preg_match( '/^\/' . DEV_LOG_URL_PATH . '\/track\/view\/(?<name>.*)?$/', $route, $matches ) ) {

			$this->actionTrackView( $matches['name'] );

			die();
		}

		if ( preg_match( '/^\/' . DEV_LOG_URL_PATH . '\/track\/delete\/(?<name>.*)?$/', $route, $matches ) ) {

			$this->actionTrackDelete( $matches['name'] );

			die();
		}

		return true;
	}


	/**
	 * Action
	 * @throws \Exception
	 */
	public function actionDefault() {

		$instances = \DevLog\DataMapper\Mappers\Log::get( [ 'data' ], [], [ 'logs.id' => 'DESC' ] )->getList();
		$this->render( 'list', [
			'instances' => $instances,
		], false );
	}

	/**
	 * @param $name
	 *
	 * @throws \Exception
	 */
	public function actionView( $name ) {

		$instance = \DevLog\DataMapper\Mappers\Log::get( [ 'data' ], [ [ 'logs.name', '=', $name ] ] )->one();

		if ( $instance == null ) {
			throw new \Exception( 'Not Found', 404 );
		}

		$this->render( 'view', [
			'instance' => $instance
		], false );
	}

	/**
	 * @param $name
	 *
	 * @throws \Exception
	 */
	public function actionInline( $name ) {

		$this->layout = $this->viewsDirectory . '/layout-clean.php';

		$this->render( 'inline', [
			'instance' => Log::get(['data'],['name'=>$name])->one(),
			'name'     => $name,
		], false );
	}


	/**
	 * @param $file
	 * @param array $params
	 *
	 * @param bool $clean
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function render( $file, $params = [], $clean = true ) {

		$file = $this->viewsDirectory . '/' . $file . '.php';

		if ( ! file_exists( $file ) ) {
			throw new \Exception( "View file not exist!" );
		}


		foreach ( $params as $key => $param ) {
			$$key = $param;
		}
		unset( $params );


		ob_start();
		include_once( $file );
		$content = ob_get_contents();
		ob_get_clean();

		if ( $clean == false ) {

			if ( ! file_exists( $this->layout ) ) {
				throw new \Exception( "Layout view file not exist!" );
			}

			require $this->layout;

		} else {
			echo $content;
		}

		return true;
	}


	public function redirect( $url ) {
		if ( $url == '' ) {
			$url = DevLogHelper::getActualUrlFromServer( $_SERVER );
		}

		header( 'Location: ' . $url, true, 303 );

		exit();
	}

}