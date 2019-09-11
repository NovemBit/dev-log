<?php

namespace DevLog\DataMapper\Mappers;

use DevLog\DataMapper\Models\LogData;
use DevLog\DataMapper\Models\LogList;
use DevLog\DataMapper\Models\LogMessage;
use DevLog\DevLog;
use PDO;
use PDOException;

class Log {

	/**
	 * @param \DevLog\DataMapper\Models\Log $log
	 *
	 * @throws \Exception
	 */
	public static function save( \DevLog\DataMapper\Models\Log $log ) {

		$db = DevLog::getDb();

		$db->beginTransaction();

		$stmt = $db->prepare( 'INSERT INTO logs (`name`, `type`) VALUES (:name, :type)' );

		$stmt->execute( [ ':name' => $log->getName(), ':type' => $log->getType() ] );

		$log_id = $db->lastInsertId();

		$data = $log->getDataList()->getList();

		foreach ( $data as $item ) {

			$sql = 'INSERT INTO logs_data (`log_id`, `key`, `value`) VALUES (:log_id, :key, :value)';

			$stmt = $db->prepare( $sql );
			$stmt->bindValue( ':log_id', $log_id );
			$stmt->bindValue( ':key', $item->getKey() );
			$stmt->bindValue( ':value', $item->getValue( LogData::STRING ) );

			$stmt->execute();
		}


		$messages = $log->getMessageList()->getList();

		foreach ( $messages as $item ) {

			$sql = 'INSERT INTO logs_messages (`log_id`, `type`, `message`,`category`, `time`) VALUES (:log_id, :type, :message, :category, :time)';

			$stmt = $db->prepare( $sql );
			$stmt->bindValue( ':log_id', $log_id );
			$stmt->bindValue( ':type', $item->getType() );
			$stmt->bindValue( ':message', $item->getMessage( LogMessage::STRING ) );
			$stmt->bindValue( ':category', $item->getCategory() );
			$stmt->bindValue( ':time', $item->getTime() );

			$stmt->execute();

		}

		$db->commit();
	}

	/**
	 * @param array $with
	 * @param array $criteria
	 * @param array $order
	 *
	 * @return LogList
	 * @throws \Exception
	 */
	public static function get( array $with = [], array $criteria = [], array $order = [] ) {

		$db = DevLog::getDb();

		/*
		 * Select fields as array
		 * */
		$select = [
			"logs.id",
			"logs.name",
			"logs.type",
		];

		if ( in_array( 'data', $with ) ) {
			$select = array_merge( $select, [
				"logs_data.id"    => "data_id",
				"logs_data.key"   => "data_key",
				"logs_data.value" => "data_value",
			] );
		}
		if ( in_array( 'messages', $with ) ) {
			$select = array_merge( $select, [
				"logs_messages.id"       => "message_id",
				"logs_messages.type"     => "message_type",
				"logs_messages.message"  => "message_message",
				"logs_messages.category" => "message_category",
				"logs_messages.time"     => "message_time",
			] );
		}

		$fields = '';
		foreach ( $select as $key => $field ) {
			if ( is_string( $key ) ) {
				$fields .= $key . " AS " . $field;
			} else {
				$fields .= $field;
			}

			if ( next( $select ) == true ) {
				$fields .= ', ';
			}
		}

		/*
		 * Building where statement
		 * */
		$where = '';
		foreach ( $criteria as $value ) {
			if ( is_array( $value ) ) {
				$where .= "$value[0] $value[1] :" . crc32( $value[0] );
				if ( next( $criteria ) == true ) {
					$where .= isset( $value[3] ) ? $value[3] : ' AND ';
				}
			}
		}
		$where = $where != '' ? "WHERE " . $where : '';


		/*
		 * Building order by statement
		 * */
		$order_by = '';
		foreach ( $order as $key => $value ) {

			$order_by .= $key . ' ' . $value;

			if ( next( $order ) == true ) {
				$order_by .= ', ';
			}
		}
		$order_by = $order_by != '' ? "ORDER BY " . $order_by : '';


		$sql = "SELECT $fields FROM logs";
		if ( in_array( 'data', $with ) ) {
			$sql .= " LEFT JOIN logs_data ON logs.id=logs_data.log_id";
		}
		if ( in_array( 'messages', $with ) ) {
			$sql .= " LEFT JOIN logs_messages ON logs.id=logs_messages.log_id";
		}

		$sql .= " $where $order_by";


		$stmt = $db->prepare( $sql );

		foreach ( $criteria as $value ) {
			if ( is_array( $value ) ) {
				$stmt->bindValue( ":" . crc32( $value[0] ), $value[2] );
			}
		}

		$stmt->execute();


		$list = new LogList();

		$log_id       = null;
		$messages_ids = [];
		$data_ids     = [];

		while ( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ) {

			if ( $log_id != $row['id'] ) {
				/** @var \DevLog\DataMapper\Models\Log $log */
				$list->addLog( new \DevLog\DataMapper\Models\Log( $row['id'], $row['name'], $row['type'] ) );
				$log          = $list->one( true );
				$log_id       = $row['id'];
				$messages_ids = [];
				$data_ids     = [];
			}

			/** @var \DevLog\DataMapper\Models\Log $log */
			if ( in_array( 'data', $with ) && ! empty( $row['data_key'] ) && ! isset( $data_ids[$row['data_id']] ) ) {
				$data_ids[$row['data_id']] = true;
				$log->getDataList()->addData( new LogData( $row['data_id'], $row['data_key'], $row['data_value'] ) );
			}

			/** @var \DevLog\DataMapper\Models\Log $log */
			if ( in_array( 'messages', $with ) && ! empty( $row['message_type'] ) && ! isset( $messages_ids[$row['message_id']] ) ) {
			    $messages_ids[$row['message_id']] = true;
				$log->getMessageList()->addMessage(
					new LogMessage( $row['message_id'], $row['message_type'], $row['message_message'], $row['message_category'], $row['message_time'] )
				);
			}
		}

		return $list;

	}

}
