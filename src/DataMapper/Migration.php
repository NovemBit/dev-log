<?php

namespace DevLog\DataMapper;


use DevLog\DevLog;
use Exception;

class Migration {


	/**
	 * @throws Exception
	 */
	public static function mysql() {
		$sql = file_get_contents( __DIR__ . '/Migration/mysql.sql' );
		if ( DevLog::getDb()->exec( $sql ) ) {
			return true;
		}

		return false;
	}


	/**
	 * @return bool
	 * @throws Exception
	 */
	public static function sqlite() {
		$sql = file_get_contents( __DIR__ . '/Migration/sqlite.sql' );
		if ( DevLog::getDb()->exec( $sql ) ) {
			return true;
		}

		return false;
	}

}
