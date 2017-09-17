<?php namespace App\Http;

use App\DoctrineODM\Paginator;
use App\Entities\Model;
use App\Exceptions\BaseException;
use App\Exceptions\Validation\ValidationException;
use App\Http\Middleware\Locale;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Doctrine\MongoDB\CursorInterface;
use Doctrine\ODM\MongoDB\Query\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Input;

class ResponseObject implements Jsonable, Arrayable
{
    const DEFAULT_STATUS = 200;

    /* @var array $payload */
    private $payload = [];

    /* @var int $status */
    private $status = 0;

    /* @var BaseException[] $errors */
    private $errors = [];

    /* @var array $headers */
    private $headers = []; // TODO: use headers in response (may need to override default ResponseFactory?)

    /* @var array $response_mapping */
    private $response_mapping = [];

    /**
     * ResponseObject constructor.
     * @param mixed $payload
     * @param int $status
     * @param array $headers
     */
    public function __construct($payload = [], int $status = 0, array $headers = [])
    {
        $this->setPayload($payload)
            ->setHeaders($headers);

        if ($status != 0) {
            $this->setStatus($status);
        }
    }

    /**
     * @return BaseException[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Adds Exception instances to array of exceptions
     * @param \Throwable[] $exceptions
     * @return ResponseObject
     */
    public function addException(\Throwable ...$exceptions): ResponseObject
    {
        foreach ($exceptions as $exception) {
            // TODO: ResponseObject will need to create a BaseException representation if it doesn't exist and it is being returned from the controller
//            if ($exception instanceof BaseException) {
//                array_push($this->errors, $exception);
//            } else {
//                array_push($this->errors, BaseException::createFromThrowable($exception));
//            }
            if ($exception instanceof \Throwable) {
                array_push($this->errors, $exception);
            }
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @param mixed $payload
     * @return ResponseObject
     */
    public function setPayload($payload): ResponseObject
    {
        // If an instance of builder paginate results
        if ($payload_value = $this->formatPayload($payload)) {
            $this->payload = $payload_value;
        } elseif (is_array($payload) || is_null($payload)) {
            $this->payload = $payload;
        } elseif ($payload instanceof Arrayable) {
            $this->payload = $payload->toArray();
        } else {
            $this->payload = $payload;
        }
        // MOVE to service: formatPayload method
//        if ($payload instanceof Builder || Input::get('page') && !is_null($payload)) {
//            $paginated_payload = $this->createPaginatedPayload($payload);
//            $this->payload = $paginated_payload->toArray();
//        } elseif ($payload instanceof CursorInterface) {
//            $this->payload = array_values($payload->toArray());
//        }
        return $this;
    }

    public function setPrePaginatedPayload(
        $payload,
        int $total = null,
        int $page = null,
        int $per_page = null
    ): ResponseObject {
        if ($total || $per_page || $page || Input::get('page')) {
            $pre_paginated_payload = $this->createPaginatedPayload(
                $payload,
                $total,
                $page,
                $per_page
            );

            $this->payload = $pre_paginated_payload->toArray();
        } else {
            $this->setPayload($payload);
        }

        return($this);
    }

    /**
     * Add key-value to Array Payload
     * @param mixed $key
     * @param mixed $value
     * @return ResponseObject
     * @throws BaseException
     */
    public function addToPayload($key, $value): ResponseObject
    {
        // TODO: check if Laravel hook exists on payload add
//        if (!is_array($this->payload)) {
//            throw ValidationException::create(ERR300_CANNOT_ADD_TO_NON_ARRAY_PAYLOAD);
//        }
        $this->payload[$key] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param array $headers
     * @return ResponseObject
     */
    public function setHeaders(array $headers): ResponseObject
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status == null ? $this->calculateStatus() : $this->status;
    }

    /**
     * @param int $status
     * @return ResponseObject
     */
    public function setStatus(int $status): ResponseObject
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Returns the highest level of errors encountered, or the default status code
     * @return int
     */
    private function calculateStatus(): int
    {
        if ($this->getErrors()) {
            $max_status_code = 0;
            /* @var BaseException $exception */
            foreach ($this->getErrors() as $exception) {
                $max_status_code = max($max_status_code, $exception->getStatusCode());
            }
            return $max_status_code ? $max_status_code : self::DEFAULT_STATUS;
        }
        return self::DEFAULT_STATUS;
    }

    /**
     * Convert the object to its JSON representation.
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        // Get paginated payload
        $payload = $this->toArray($this->getPayload());
        if (is_array($payload) && empty($payload)) {
            $payload = null;
        }
        $response = ['response' => $payload];

        // Add the encountered error messages
        if ($this->getErrors()) {
            $response['errors'] = [];
            foreach ($this->getErrors() as $exception) {
                // Determine the field related to the error
                $field = $exception->getErrorField();
                $field = strlen($field) ? $field : 'general';

                // If field-related error isn't set yet, set one
                if (!array_key_exists($field, $response['errors'])) {
                    $response['errors'][$field]['field'] = $field;
                    $response['errors'][$field]['error_code'] = 0;
                    $response['errors'][$field]['description'] = [];
                }

                // Add error information
                $response['errors'][$field]['error_code'] =
                    $exception->getCode() > $response['errors'][$field]['error_code'] ?
                        $exception->getCode() : $response['errors'][$field]['error_code'];
                $response['errors'][$field]['description'][] =
                    $exception->getExceptionMessage()->getTranslated()->getMessage(false);
            }

            // If there are general errors, set the field name to 'null'
            if (array_key_exists('general', $response['errors'])) {
                $response['errors']['general']['field'] = null;
            }

            // Remove the indexes of the errors
            $response['errors'] = array_values($response['errors']);

            // Apply 'array_unique' to all error descriptions
            $response['errors'] = array_map(function ($error) {
                $error['description'] = array_unique($error['description']);
                return $error;
            }, $response['errors']);
        }
        $result = json_encode($response, $options);
        return $result;
    }

    /**
     * Converts payload data to an array or a string
     * @param mixed $object
     * @param string $trans_output_format
     * @param string|null $locale
     * @return array|null|string
     */
    public function toArray($object = null, string $trans_output_format = 'default', string $locale = null)
    {
        if (!$trans_output_format || !in_array($trans_output_format, Locale::OUTPUT_FORMATS)) {
            $trans_output_format = \Config::get('LOCALE_OUTPUT_FORMAT');
        }

        if ($object instanceof \DateTime || $object instanceof Carbon) {
            return [
                "date"     => $object->format(\DateTime::ISO8601),
                "timezone" => $object->getTimezone()->getName()
            ];
        } elseif ($object instanceof \MongoId) {
            return (string)$object;
        } elseif ($object instanceof \MongoDate) {
            return $object->toDateTime()->format(\DateTime::ISO8601);
        } elseif (is_array($object)
            || $object instanceof Collection
            || $object instanceof Arrayable
            || $object instanceof CursorInterface) {
            $collection = $object;
            if ($object instanceof Collection || $object instanceof Arrayable || $object instanceof CursorInterface) {
                if ($object instanceof Model) {
                    $collection = $locale
                        ? $object->getTranslation($locale, $trans_output_format)
                            ->toArray($this->getResponseFormatsForModels(), $trans_output_format, $locale)
                        : $object->getTranslated($trans_output_format)
                            ->toArray($this->getResponseFormatsForModels(), $trans_output_format);
                } elseif ($object instanceof CursorInterface) {
                    $collection = array_values($object->toArray());
                } else {
                    $collection = $object->toArray();
                }
            }
            $elements = [];
            if (!$this->isAssoc($collection)) {
                foreach ($collection as $element) {
                    $elements[] = $this->toArray($element, $trans_output_format, $locale);
                }
            } else {
                // must be associative
                foreach ($collection as $key => $value) {
                    $elements[$key] = $this->toArray($value, $trans_output_format, $locale);
                }
            }
            unset($elements['translations']);   // Unset the translations array, we should never pass that out
            return $elements;
        }
        return $object;
    }

    /**
     * Confirms if an array is associative or numerically indexed
     * Reference: http://stackoverflow.com/a/173479/24731
     * @param array $array
     * @return bool
     */
    private function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Sets the Response Format for Models
     * @param array $mapping
     * @return ResponseObject
     */
    public function setResponseFormatsForModels(array $mapping): ResponseObject
    {
        $this->response_mapping = $mapping;
        return $this;
    }

    /**
     * Sets the Response Format for a Model type
     * @param string $model_class
     * @param string $format
     * @return ResponseObject
     */
    public function setResponseFormatForModel(string $model_class, string $format): ResponseObject
    {
        $mappings = $this->getResponseFormatsForModels() ?: [];
        $mappings[$model_class] = $format;
        $this->setResponseFormatsForModels($mappings);
        return $this;
    }

    /**
     * Returns the Response Format
     * @return array
     */
    public function getResponseFormatsForModels(): array
    {
        return $this->response_mapping;
    }

    /**
     * Returns the Response Format for a specific Model type
     * @param string $model_class
     * @return array|string|null
     */
    public function getResponseFormatForModel(string $model_class)
    {
        if (!isset($this->response_mapping[$model_class])) {
            return null;
        }
        return $this->response_mapping[$model_class];
    }

    private function createPaginatedPayload(
        $payload,
        int $total = null,
        int $page = null,
        int $per_page = null
    ): Paginator {
        return(new Paginator($payload, $total, $page, $per_page));
    }
}
