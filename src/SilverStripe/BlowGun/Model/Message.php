<?php namespace SilverStripe\BlowGun\Model;

class Message {

	/**
	 * @var array
	 */
	protected $rawMessage = [];

	/**
	 * @var bool
	 */
	protected $valid = true;

	/**
	 * @var string
	 */
	protected $errorMessage = '';

	/**
	 * @var string
	 */
	protected $action = '';

	/**
	 * @var string
	 */
	protected $responseQueue;

	/**
	 * @var string
	 */
	protected $receiptHandle;

	/**
	 * @var string
	 */
	protected $messageId;

	/**
	 * @var array
	 */
	protected $arguments;

	/**
	 * @var string
	 */
	private $queue;

	/**
	 * @param string $fromQueue
	 * @internal param array $message
	 */
	public function __construct($fromQueue) {
		$this->queue = $fromQueue;
	}

	/**
	 * @param array $rawMessage
	 */
	public function load(array $rawMessage) {
		$body = json_decode($rawMessage['Body'], true);
		$error = $this->jsonErrorMessage();
		if ($error) {
			$this->valid = false;
			$this->errorMessage = $error;
			return;
		}

		$this->messageId = $rawMessage['MessageId'];

		// do a simple parsing if we can handle this message
		if(!(isset($body['action']) && is_string($body['action']))) {
			$this->valid = false;
			$this->errorMessage = 'No \'action\' field in recieved message';
			return;
		}
		$this->action = $body['action'];

		if(isset($body['arguments']) && is_array($body['arguments'])) {
			$this->arguments = $body['arguments'];
		}

		$this->receiptHandle = $rawMessage['ReceiptHandle'];

		if(isset($body['responseQueue'])) {
			$this->responseQueue = $body['responseQueue'];
		}
	}

	/**
	 * @param $name
	 * @return string|null
	 */
	public function getArgument($name) {
		if(isset($this->arguments[$name])) {
			return $this->arguments[$name];
		}
		return null;
	}

	public function setArgument($name, $value) {
		$this->arguments[$name] = $value;
	}

	/**
	 * @return boolean
	 */
	public function isValid()
	{
		return $this->valid;
	}

	/**
	 * @return string
	 */
	public function getErrorMessage() {
		return $this->errorMessage;
	}

	/**
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}


	public function setAction($action) {
		$this->action = $action;
	}

	/**
	 * @return string
	 */
	public function getResponseQueue() {
		return $this->responseQueue;
	}

	/**
	 * @return string
	 */
	public function getQueue() {
		return $this->queue;
	}

	/**
	 * @return string
	 */
	public function getReceiptHandle() {
		return $this->receiptHandle;
	}

	/**
	 * @return string
	 */
	public function getMessageId()
	{
		return $this->messageId;
	}

	public function getRawBody() {
		$rawBody = array();
		$rawBody['action'] = $this->action;
		$rawBody['arguments'] = $this->arguments;
		// @todo(stig): add the respond to queue
		return json_encode($rawBody, JSON_PRETTY_PRINT);
	}

	/**
	 * Return last JSON error message or null.
	 * Unfortunately json_last_error_msg only available in PHP>=5.5.
	 *
	 * @return null|string Message
	 */
	protected function jsonErrorMessage() {
		$error = json_last_error();
		if (!$error) return null;

		switch ($error) {
			case JSON_ERROR_NONE:
				return 'No errors';
				break;
			case JSON_ERROR_DEPTH:
				return 'Maximum stack depth exceeded';
				break;
			case JSON_ERROR_STATE_MISMATCH:
				return 'Underflow or the modes mismatch';
				break;
			case JSON_ERROR_CTRL_CHAR:
				return 'Unexpected control character found';
				break;
			case JSON_ERROR_SYNTAX:
				return 'Syntax error, malformed JSON';
				break;
			case JSON_ERROR_UTF8:
				return 'Malformed UTF-8 characters, possibly incorrectly encoded';
				break;
			default:
				return 'Unknown error';
				break;
		}
	}
}