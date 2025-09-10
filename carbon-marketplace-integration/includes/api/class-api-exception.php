<?php
/**
 * API Exception Class
 *
 * Custom exception class for API-related errors.
 *
 * @package CarbonMarketplace
 * @subpackage API
 */

namespace CarbonMarketplace\API;

use Exception;

/**
 * API Exception class
 */
class ApiException extends Exception {

    /**
     * HTTP status code
     *
     * @var int
     */
    protected $status_code;

    /**
     * API response data
     *
     * @var array
     */
    protected $response_data;

    /**
     * API endpoint that caused the error
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @param array $response_data API response data
     * @param string $endpoint API endpoint
     * @param Exception $previous Previous exception
     */
    public function __construct($message = '', $status_code = 0, $response_data = array(), $endpoint = '', Exception $previous = null) {
        parent::__construct($message, $status_code, $previous);
        
        $this->status_code = $status_code;
        $this->response_data = $response_data;
        $this->endpoint = $endpoint;
    }

    /**
     * Get HTTP status code
     *
     * @return int Status code
     */
    public function getStatusCode() {
        return $this->status_code;
    }

    /**
     * Get API response data
     *
     * @return array Response data
     */
    public function getResponseData() {
        return $this->response_data;
    }

    /**
     * Get API endpoint
     *
     * @return string Endpoint
     */
    public function getEndpoint() {
        return $this->endpoint;
    }

    /**
     * Check if error is retryable
     *
     * @return bool True if retryable
     */
    public function isRetryable() {
        return $this->status_code >= 500 || $this->status_code === 429;
    }

    /**
     * Check if error is a rate limit error
     *
     * @return bool True if rate limited
     */
    public function isRateLimited() {
        return $this->status_code === 429;
    }

    /**
     * Check if error is an authentication error
     *
     * @return bool True if authentication error
     */
    public function isAuthError() {
        return $this->status_code === 401 || $this->status_code === 403;
    }

    /**
     * Get error details as array
     *
     * @return array Error details
     */
    public function toArray() {
        return array(
            'message' => $this->getMessage(),
            'status_code' => $this->status_code,
            'endpoint' => $this->endpoint,
            'response_data' => $this->response_data,
            'is_retryable' => $this->isRetryable(),
            'is_rate_limited' => $this->isRateLimited(),
            'is_auth_error' => $this->isAuthError()
        );
    }
}