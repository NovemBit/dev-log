<?php

namespace DevLog\DataMapper;


use DevLog\DevLog;

class Migration {


	/**
	 * @throws \Exception
	 */
	public static function mysql(){
		$sql = file_get_contents('Migration/mysql.sql');
		if(DevLog::getDb()->exec($sql)){
			return true;
		}
		return false;
	}


	/**
	 * @return bool
	 * @throws \Exception
	 */
	public static function sqlite(){
		$sql = file_get_contents('Migration/sqlite.sql');
		if(DevLog::getDb()->exec($sql)){
			return true;
		}
		return false;
	}

}
