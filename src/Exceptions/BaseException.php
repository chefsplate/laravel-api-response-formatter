<?php

namespace ChefsPlate\API\Exceptions;

use Exception;

class BaseException extends Exception
{
    const ERROR_NOT_FOUND = "Not Found";
    const ERROR_NOT_ACCEPTABLE = "Not Acceptable";

    /* @var string $field_name */
    protected $field_name;

    /* @var string|array $description */
    protected $description;

    /* @var string $error_code */
    protected $error_code;

    /* @var int $status_code */
    protected $status_code = 400;

    public function __construct($message = "", $code = 0, Exception $previous = null)
    {

        $exception_code    = is_int($code) ? $code : 0;
        $exception_message = is_string($message) ? $message : "";
        parent::__construct($exception_message, $exception_code, $previous);
        $this->setDescription($message)
            ->setErrorCode($code);
    }

    public static function convertPhpToBaseException(Exception $exception)
    {
        if ($exception instanceof BaseException) {
            return $exception;
        }
        return new BaseException($exception->getMessage(), $exception->getCode(), $exception); // previous = current
    }

    public function toArray()
    {
        return array(
            'field'       => $this->getFieldName(),
            'error_code'  => $this->getErrorCode(),
            'description' => (array)($this->getDescription())
        );
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->field_name;
    }

    /**
     * @param string $field_name
     *
     * @return BaseException
     */
    public function setFieldName($field_name)
    {
        $this->field_name = $field_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getErrorCode()
    {
        return $this->error_code;
    }

    /**
     * @param string $error_code
     *
     * @return BaseException
     */
    public function setErrorCode($error_code)
    {
        $this->error_code = $error_code;
        return $this;
    }

    /**
     * @return string|array
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string|array $description
     *
     * @return BaseException
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->status_code;
    }

    /**
     * @param int $status_code
     *
     * @return BaseException
     */
    public function setStatusCode($status_code)
    {
        $this->status_code = $status_code;
        return $this;
    }
}
