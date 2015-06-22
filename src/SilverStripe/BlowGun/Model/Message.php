<?php
namespace SilverStripe\BlowGun\Model;

class Message {

	/**
	 * @var array
	 */
	protected $rawMessage = array();

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
	protected $type = '';

	/**
	 * @var string
	 */
	protected $respondTo;

	/**
	 * @var string
	 */
	protected $responseId;

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
	 * @var bool
	 */
	protected $success;

	/**
	 * @var string
	 */
	protected $message;

	/**
	 * @var string
	 */
	private $queue;

	/**
	 * @param string $fromQueue
	 */
	public function __construct($fromQueue) {
		$this->queue = $fromQueue;
	}

	/**
	 * @param array $rawMessage
	 */
	public function load(array $rawMessage) {

		// get all the SQS.Message properties
		$this->messageId = $rawMessage['MessageId'];
		$this->receiptHandle = $rawMessage['ReceiptHandle'];

		// then parse the body into this object
		$body = json_decode($rawMessage['Body'], true);
		$error = $this->jsonErrorMessage();
		if($error) {
			$this->valid = false;
			$this->errorMessage = $error;
			return;
		}

		// type is critical for a Message
		if(!(isset($body['type']) && is_string($body['type']))) {
			$this->valid = false;
			$this->errorMessage = 'No \'type\' field in recieved message';
			return;
		}
		$this->type = $body['type'];

		if (isset($body['success'])) {
			$this->success = $body['success'];
		}
		if (isset($body['message'])) {
			$this->message = $body['message'];
		}
		if (isset($body['error_message'])) {
			$this->errorMessage = $body['error_message'];
		}

		// Chuck all the arguments into this class
		if(isset($body['arguments']) && is_array($body['arguments'])) {
			$this->arguments = $body['arguments'];
		}

		if(isset($body['respond_to'])) {
			$this->respondTo = $body['respond_to'];
		}
		if(isset($body['response_id'])) {
			$this->responseId = $body['response_id'];
		}
	}

	/**
	 * @todo: It would be nice if this is actually checking the values instead
	 * of relying on a property
	 *
	 *
	 * @return boolean
	 */
	public function isValid() {
		return $this->valid;
	}

	/**
	 * @return string
	 */
	public function getQueue() {
		return $this->queue;
	}

	/**
	 * @return bool
	 */
	public function getSuccess() {
		return $this->success;
	}

	/**
	 * @param bool $success
	 * @return Message
	 */
	public function setSuccess($success) {
		$this->success = $success;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * @param string $message
	 * @return Message
	 */
	public function setMessage($message) {
		$this->message = $message;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getErrorMessage() {
		return $this->errorMessage;
	}

	/**
	 * @param $msg
	 */
	public function setErrorMessage($msg) {
		$this->errorMessage = $msg;
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

	/**
	 * @return array
	 */
	public function getArguments() {
		return $this->arguments;
	}

	/**
	 * @param $name
	 * @param $value
	 * @return Message
	 */
	public function setArgument($name, $value) {
		$this->arguments[$name] = $value;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param $type
	 * @return Message
	 */
	public function setType($type) {
		$this->type = $type;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getRespondTo() {
		return $this->respondTo;
	}

	/**
	 * @param string $queueName
	 * @param string $id
	 * @return Message
	 */
	public function setRespondTo($queueName, $id) {
		$this->respondTo = $queueName;
		$this->responseId = $id;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getResponseId() {
		return $this->responseId;
	}

	/**
	 * @param string $responseId
	 * @return Message
	 */
	public function setResponseId($responseId) {
		$this->responseId = $responseId;
		return $this;
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
	public function getMessageId() {
		return $this->messageId;
	}

	/**
	 * @return string
	 */
	public function getAsJson() {
		$rawBody = array();
		$rawBody['type'] = $this->type;
		$rawBody['success'] = $this->success;

		if(count($this->arguments)) {
			$rawBody['arguments'] = $this->arguments;
		}
		if($this->respondTo) {
			$rawBody['respond_to'] = $this->respondTo;
		}
		if (!empty($this->responseId)) {
			$rawBody['response_id'] = $this->responseId;
		}

		if (!empty($this->message)) {
			$rawBody['message'] = $this->message;
		}
		if (!empty($this->errorMessage)) {
			$rawBody['error_message'] = $this->errorMessage;
		}
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
		if(!$error) return null;

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
