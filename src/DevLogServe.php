<?php

namespace DevLog;
/*
 * Dev log
 * Simple and Powerful debugging tool
 * */

use DevLog\DataMapper\DB;
use DevLog\DataMapper\Models\Log;
use DevLog\DataMapper\Models\LogData;
use Dompdf\Exception;

class DevLogServe {

	public $log;

	public $name = null;

	public $path = null;

	public $events = [];

	public $trackers = [];

	public $max_served_logs_count = 200;

	/*
	 * Private properties
	 * */
	private $trackersServe = null;

	/**
	 * DevLogServe constructor.
	 *
	 * @param Log $log
	 * @param array $trackers
	 * @param DevLogServe $trackersServe
	 */
	public function __construct( Log $log, $trackers = [], $trackersServe = null ) {
		$this->setLog( $log );
		$this->trackersServe = $trackersServe;
//		$this->setTrackers( $trackers );
	}

	/**
	 * @return Log
	 */
	public function getLog(): Log {
		return $this->log;
	}

	/**
	 * @param Log $log
	 */
	public function setLog( Log $log ) {
		$this->log = $log;
	}


	/**
	 * Setting tracker functions
	 *
	 * @param $trackers
	 */
	private function setTrackers( $trackers ) {
		/*
		 * Adding event before save
		 * To track a data
		 * */
		/**
		 * @param $log
		 * @param $status
		 */
		$this->events['beforeSave'][] = function ( & $log, & $status ) use ( $trackers ) {

			/*
			 * Fetching trackers
			 * */
			foreach ( $trackers as $key => $tracker ) {
				/*
				 * If is set criteria
				 * */
				$criteria = isset( $tracker['criteria'] ) ? $tracker['criteria'] : [];

				/*
				 * Final status of criteria
				 * */
				$condition_status = true;

				/*
				 * Fetching criteria and checking each condition
				 * */
				foreach ( $criteria as $condition ) {

					/*
					 * If condition first element is callable function
					 * */
					if ( is_callable( $condition[0] ) ) {

						/*
						 * Type of condition (and | or)
						 * */
						$condition_type = isset( $condition[1] ) ? strtolower( $condition[1] ) : 'and';
						if ( $condition_type == 'and' ) {
							$condition_status = $condition_status && call_user_func_array( $condition[0], [
									& $log,
									& $status
								] );
						} elseif ( $condition_type == 'or' ) {
							$condition_status = $condition_status || call_user_func_array( $condition[0], [
									& $log,
									& $status
								] );
						}
					}
				}

				/*
				 * If criteria condition checking is successful
				 * And is true
				 * */
				if ( $condition_status == true ) {
					$to_serve = [];

					$group_by = isset( $tracker['group_by'] ) ? $tracker['group_by'] : [ 'default' => [] ];

					$data = isset( $tracker['data'] ) ? $tracker['data'] : false;

					foreach ( $group_by as $group ) {
						$to_serve[ $group ] = isset( $to_serve[ $group ] ) ? $to_serve[ $group ] : [];

						/*
						 * If data is function then call this function
						 * and returned data use for serving
						 * */
						if ( is_callable( $data ) ) {
							$old_tracker_instance = $this->trackersServe->findOne( $key )->log;
							$to_serve             = call_user_func_array( $data, [
								$key,
								$group,
								isset( $old_tracker_instance ) ? $old_tracker_instance : [],
								$log,
							] );
						}

						if ( isset( $tracker['serve_instances'] ) ) {
							if ( $tracker['serve_instances'] == true ) {
								/*
								 * Adding parent log instance hash
								 * */
								if ( ! isset( $to_serve[ $group ]['instances'] ) ) {
									$to_serve[ $group ]['instances'] = [];
								}
								$to_serve[ $group ]['instances'][] = $log->name;
							} else {
								unset( $to_serve[ $group ]['instances'] );
							}
						}

						if ( isset( $tracker['serve_instances_count'] ) ) {
							if ( $tracker['serve_instances_count'] == true ) {
								/*
								 * Adding parent log instance hash
								 * */
								if ( ! isset( $to_serve[ $group ]['instances_count'] ) ) {
									$to_serve[ $group ]['instances_count'] = 0;
								}
								$to_serve[ $group ]['instances_count'] ++;
							} else {
								unset( $to_serve[ $group ]['instances_count'] );
							}
						}

						/*
						 * If Tracker serve exists
						 * */
						if ( $this->trackersServe !== null ) {
							$this->trackersServe->log  = $to_serve;
							$this->trackersServe->name = $key;
							$this->trackersServe->save();
						}
					}


				}

			}
		};


	}


	/**
	 * @param $name
	 * @param $status
	 */
	private function runEvent( $name, & $status ) {
		if ( isset( $this->events[ $name ] ) ) {
			foreach ( $this->events[ $name ] as $event ) {
				call_user_func_array( $event, [ &$this, &$status ] );
			}
		}
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function save() {
		$status = true;

		$this->runEvent( 'beforeSave', $status );

		$db = ( new DB() )->db;

		$db->exec( 'INSERT INTO logs (name) VALUES ("' . $this->getLog()->getName() . '")' );

		$log_id = $db->lastInsertRowID();

		$data = $this->getLog()->getDataList();

		/** @var LogData $item */
		foreach ( $data as $item ) {

			$sql = 'INSERT INTO logs_data (`log_id`, `key`, `value`) VALUES (:log_id, :key, :value)';

			$stmt = $db->prepare( $sql );
			$stmt->bindValue( ':log_id', $log_id );
			$stmt->bindValue( ':key', $item->getKey() );
			$stmt->bindValue( ':value', $item->getValue() );

			$ret = $stmt->execute();

		}

		$db->close();

		$status = true;

		$this->runEvent( 'afterSave', $status );

		return $status;
	}

	/**
	 * @return string|null
	 */
	private function getFilePath() {
		if ( $this->name == null || $this->path == null ) {
			return null;
		};

		if ( ! file_exists( $this->path ) ) {
			mkdir( $this->path, 0777, true );
		}

		return $this->path . "/" . $this->name;
	}

	/**
	 * @param $name
	 *
	 * @return $this|bool
	 * @throws \Exception
	 */
	public function findOne( $name ) {
		$model       = clone $this;
		$model->name = $name;

		$path = $model->getFilePath();
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		$model->log = DevLogHelper::jsonDecode( file_get_contents( $path ) );

		return $model;
	}

	/**
	 * @return bool
	 */
	public function delete() {
		if ( unlink( $this->getFilePath() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return bool
	 */
	public function deleteWithRange( $offset = 0, $limit = 0 ) {
		$files = array_slice( $this->getAllFiles(), $offset, $limit );
		foreach ( $files as $file ) {
			if ( is_file( $file ) && is_writeable( $file ) ) {
				unlink( $file );
			};
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function deleteAll() {
		return DevLogHelper::deleteDir( $this->path );
	}

	/**
	 * @return array|false
	 */
	public function getAllFiles() {
		$files = glob( $this->path . '/*' );
		usort( $files, function ( $a, $b ) {
			return @filemtime( $a ) < @filemtime( $b );
		} );

		return $files;
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function findAll( $offset = 0, $limit = 100 ) {
		$files = self::getAllFiles();
		$files = array_slice( $files, $offset, $limit );

		$result = [];
		foreach ( $files as $file ) {
			$name            = basename( $file );
			$instance        = $this->findOne( $name );
			$result[ $name ] = $this->findOne( $name );
		}

		return array_filter( $result );
	}
}