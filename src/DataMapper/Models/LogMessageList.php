<?php

namespace DevLog\DataMapper\Models;

class LogMessageList {

	private $list = [];


	public function __construct() {

	}

	/**
	 * @param LogMessage $data
	 *
	 * @return LogMessage
	 */
	public function addMessage( LogMessage $data ) {
		$this->list[] = $data;

		return end( $this->list );
	}


	/**
	 * @param bool $last
	 *
	 * @return LogMessage|null
	 */
	public function one( $last = true ) {
		if ( empty( $this->list ) ) {
			return null;
		}

		if ( $last ) {
			return end( $this->list );
		} else {
			return $this->getList()[0];
		}
	}


	/**
	 * @return LogMessage[]
	 */
	public function getList() {
		return $this->list;
	}

	/**
	 * @param LogMessage[] $list
	 */
	public function setList( array $list ) {
		$this->list = $list;

	}

}
