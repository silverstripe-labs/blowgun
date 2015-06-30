<?php

namespace SilverStripe\BlowGun\Model;

class Status {

	/**
	 * @var array
	 */
	protected $errors = [];

	/**
	 * @var array
	 */
	protected $notices = [];

	/**
	 * @var array
	 */
	protected $data = [];

	/**
	 * @var bool
	 */
	protected $isSuccessful = false;

	public function __construct() {
	}

	/**
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @param $line
	 */
	public function addError($line) {
		$this->errors[] = trim($line);
	}

	/**
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * @param $key
	 * @param $value
	 */
	public function setData($key, $value) {
		$this->data[trim($key)] = trim($value);
	}

	/**
	 * @return array
	 */
	public function getNotices() {
		return $this->notices;
	}

	/**
	 * @param $line
	 */
	public function addNotice($line) {
		$this->notices[] = trim($line);
	}

	/**
	 * @return bool
	 */
	public function isSuccessful() {
		return $this->isSuccessful;
	}

	/**
	 *
	 */
	public function succeeded() {
		$this->isSuccessful = true;
	}

	/**
	 *
	 */
	public function failed() {
		$this->isSuccessful = false;
	}
}