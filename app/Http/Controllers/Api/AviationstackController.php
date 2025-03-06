<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AviationstackController extends Controller
{
    protected $apiKey;
    protected $baseUrl;
    protected $cacheTime;
    protected $debug;

    public function __construct()
    {
        // Get API key and URL from environment variables
        $this->apiKey = env('AVIATIONSTACK_API_KEY', '3f73618bf41df1cbd0cc28f50ed27670');

        // Free tier of Aviationstack only supports HTTP, paid tiers support HTTPS
        $this->baseUrl = 'http://api.aviationstack.com/v1';

        // Cache responses for 15 minutes by default
        $this->cacheTime = env('AVIATIONSTACK_CACHE_TIME', 15);

        // Enable debugging in development
        $this->debug = env('APP_DEBUG', false);

        if ($this->debug) {
            Log::info("AviationstackController initialized", [
                'baseUrl' => $this->baseUrl,
                'apiKey' => substr($this->apiKey, 0, 5) . '...' // Only log a portion for security
            ]);
        }
    }

    /**
     * Proxy request to Aviationstack API with caching
     *
     * @param Request $request
     * @param string $endpoint
     * @return \Illuminate\Http\JsonResponse
     */
    public function proxy(Request $request, $endpoint)
    {
        try {
            // Create a debug log file if we're in debug mode
            if ($this->debug) {
                $logPath = storage_path('logs');
                if (!file_exists($logPath)) {
                    mkdir($logPath, 0755, true);
                }

                $debugLog = fopen(storage_path('logs/aviationstack.log'), 'a');
                fwrite($debugLog, "\n" . date('Y-m-d H:i:s') . " - New request to {$endpoint}\n");
                fwrite($debugLog, "Parameters: " . json_encode(array_diff_key($request->all(), ['access_key' => ''])) . "\n");
                fclose($debugLog);
            }

            // Add API key to parameters
            $params = $request->all();
            $params['access_key'] = $this->apiKey;

            // Drop cache if debug parameter is present
            $forceRefresh = $request->has('debug') && $this->debug;

            // Create cache key based on endpoint and parameters
            $cacheKey = 'aviationstack_' . $endpoint . '_' . md5(json_encode($params));

            // Debug info for development
            if ($this->debug) {
                Log::info("Aviationstack API request", [
                    'endpoint' => $endpoint,
                    'url' => "{$this->baseUrl}/{$endpoint}",
                    'params' => array_diff_key($params, ['access_key' => '']),
                    'cache_key' => $cacheKey,
                    'force_refresh' => $forceRefresh
                ]);
            }

            // Define the callback function that makes the actual API request
            $apiRequestCallback = function () use ($endpoint, $params) {
                $startTime = microtime(true);

                try {
                    // Make API request with proper timeout and verification
                    $response = Http::timeout(15)
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->get("{$this->baseUrl}/{$endpoint}", $params);

                    $requestTime = microtime(true) - $startTime;

                    // Debug log raw response
                    if ($this->debug) {
                        $debugLog = fopen(storage_path('logs/aviationstack.log'), 'a');
                        fwrite($debugLog, date('Y-m-d H:i:s') . " - API Response received in {$requestTime}s\n");
                        fwrite($debugLog, "Status: " . $response->status() . "\n");
                        fwrite($debugLog, "Response: " . substr($response->body(), 0, 1000) . (strlen($response->body()) > 1000 ? "...(truncated)" : "") . "\n\n");
                        fclose($debugLog);
                    }

                    if ($this->debug) {
                        Log::debug("Aviationstack API response", [
                            'status' => $response->status(),
                            'time' => $requestTime,
                            'endpoint' => $endpoint,
                            'headers' => $response->headers(),
                            'bodySize' => strlen($response->body())
                        ]);
                    }

                    // Check for HTTP failure
                    if ($response->failed()) {
                        // Add to debug log
                        if ($this->debug) {
                            $debugLog = fopen(storage_path('logs/aviationstack.log'), 'a');
                            fwrite($debugLog, date('Y-m-d H:i:s') . " - API HTTP ERROR\n");
                            fwrite($debugLog, "Status: " . $response->status() . "\n");
                            fwrite($debugLog, "Reason: " . $response->reason() . "\n");
                            fwrite($debugLog, "Body: " . $response->body() . "\n\n");
                            fclose($debugLog);
                        }

                        Log::error("Aviationstack API HTTP error", [
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                            'reason' => $response->reason(),
                            'body' => $response->body()
                        ]);

                        return response()->json([
                            'error' => [
                                'message' => 'The Aviationstack API request failed: ' . $response->reason(),
                                'status' => $response->status(),
                                'details' => $this->debug ? $response->body() : null
                            ]
                        ], $response->status());
                    }

                    // Parse the JSON response
                    $data = $response->json();

                    // If we get to this point but data isn't an array, there might be a parsing issue
                    if (!is_array($data)) {
                        Log::error("Aviationstack API invalid JSON response", [
                            'endpoint' => $endpoint,
                            'response' => $response->body()
                        ]);

                        return response()->json([
                            'error' => [
                                'message' => 'Invalid response format from Aviationstack API',
                                'details' => $this->debug ? 'Response was not valid JSON: ' . substr($response->body(), 0, 100) : null
                            ]
                        ], 500);
                    }

                    // Check for API-specific errors in the response body
                    if (isset($data['error'])) {
                        $errorType = $data['error']['type'] ?? 'unknown';
                        $errorCode = $data['error']['code'] ?? 0;
                        $errorInfo = $data['error']['info'] ?? 'Unknown error';

                        // Add to debug log
                        if ($this->debug) {
                            $debugLog = fopen(storage_path('logs/aviationstack.log'), 'a');
                            fwrite($debugLog, date('Y-m-d H:i:s') . " - API ERROR RESPONSE\n");
                            fwrite($debugLog, "Error Type: " . $errorType . "\n");
                            fwrite($debugLog, "Error Code: " . $errorCode . "\n");
                            fwrite($debugLog, "Error Info: " . $errorInfo . "\n\n");
                            fclose($debugLog);
                        }

                        // Log the specific error for debugging
                        Log::error("Aviationstack API error response", [
                            'endpoint' => $endpoint,
                            'error_type' => $errorType,
                            'error_code' => $errorCode,
                            'error_info' => $errorInfo
                        ]);

                        // Provide user-friendly messages for common error codes
                        $userMessage = $errorInfo;

                        // Interpret common error codes
                        switch ($errorCode) {
                            case 101: // Invalid API access key
                                $userMessage = 'The API access key is invalid. Please check your configuration.';
                                break;
                            case 102: // Inactive user account
                                $userMessage = 'The user account is inactive. Please check your Aviationstack account status.';
                                break;
                            case 103: // Function access restricted
                                $userMessage = 'This function is restricted with your current subscription plan.';
                                break;
                            case 104: // Usage limit reached
                                $userMessage = 'The monthly API request limit has been reached. Please upgrade your plan.';
                                break;
                            case 105: // Function access restricted (HTTPS)
                                $userMessage = 'HTTPS access requires a paid subscription plan.';
                                break;
                            case 301: // Missing or invalid parameters
                                $userMessage = 'Some required parameters are missing or invalid. Please check your search query.';
                                break;
                            case 302: // Invalid date format
                                $userMessage = 'The date format is invalid. Please use YYYY-MM-DD format.';
                                break;
                            case 303: // No results found
                                $userMessage = 'No results were found matching your search criteria.';
                                break;
                        }

                        return response()->json([
                            'error' => [
                                'message' => $userMessage,
                                'code' => $errorCode,
                                'type' => $errorType,
                                'details' => $this->debug ? $errorInfo : null
                            ]
                        ], 400);
                    }

                    // Check if data has the expected structure
                    if (!isset($data['data'])) {
                        Log::warning("Aviationstack API unexpected response structure", [
                            'endpoint' => $endpoint,
                            'keys' => array_keys($data),
                            'response_sample' => json_encode(array_slice($data, 0, 3))
                        ]);

                        // Try to provide a meaningful response even if structure is unexpected
                        if (empty($data)) {
                            return response()->json([
                                'pagination' => ['total' => 0, 'count' => 0],
                                'data' => []
                            ]);
                        }

                        // If we have some data but not in expected format, return it as is
                        return response()->json($data);
                    }

                    // Return the successful response
                    return response()->json($data);
                } catch (\Exception $e) {
                    Log::error("Aviationstack API request exception", [
                        'endpoint' => $endpoint,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);

                    throw $e; // Re-throw to be caught by the outer try-catch
                }
            };

            // Use the cache unless forced to refresh
            if ($forceRefresh) {
                Cache::forget($cacheKey);
                return $apiRequestCallback();
            } else {
                return Cache::remember($cacheKey, $this->cacheTime * 60, $apiRequestCallback);
            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Network connection error
            Log::error("Aviationstack API connection error", [
                'endpoint' => $endpoint,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Could not connect to the Aviationstack API. Please check your internet connection and try again.',
                    'details' => $this->debug ? $e->getMessage() : null
                ]
            ], 503); // Service Unavailable
        } catch (\Exception $e) {
            // Log the exception with details
            Log::error("Aviationstack API unhandled exception", [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => [
                    'message' => 'An error occurred while fetching flight data.',
                    'details' => $this->debug ? $e->getMessage() : 'Please try again or contact support if the problem persists.'
                ]
            ], 500);
        }
    }

    /**
     * Get flights information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function flights(Request $request)
    {
        return $this->proxy($request, 'flights');
    }

    /**
     * Get routes information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function routes(Request $request)
    {
        return $this->proxy($request, 'routes');
    }

    /**
     * Get airports information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function airports(Request $request)
    {
        return $this->proxy($request, 'airports');
    }

    /**
     * Get airlines information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function airlines(Request $request)
    {
        return $this->proxy($request, 'airlines');
    }

    /**
     * Get airplanes information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function airplanes(Request $request)
    {
        return $this->proxy($request, 'airplanes');
    }

    /**
     * Get aircraft types information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function aircraftTypes(Request $request)
    {
        return $this->proxy($request, 'aircraft_types');
    }

    /**
     * Get taxes information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function taxes(Request $request)
    {
        return $this->proxy($request, 'taxes');
    }

    /**
     * Get cities information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cities(Request $request)
    {
        return $this->proxy($request, 'cities');
    }

    /**
     * Get countries information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function countries(Request $request)
    {
        return $this->proxy($request, 'countries');
    }

    /**
     * Get flight schedules information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function flightSchedules(Request $request)
    {
        return $this->proxy($request, 'flight_schedules');
    }

    /**
     * Get future flight schedules information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function futureSchedules(Request $request)
    {
        return $this->proxy($request, 'future_schedules');
    }

    /**
     * API Connection Test
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function test(Request $request)
    {
        try {
            // Create a debug log file
            $logPath = storage_path('logs');
            if (!file_exists($logPath)) {
                mkdir($logPath, 0755, true);
            }

            $debugLog = fopen(storage_path('logs/aviationstack.log'), 'a');
            fwrite($debugLog, "\n" . date('Y-m-d H:i:s') . " - API Test Connection\n");

            // Log API key (first 5 chars only for security)
            $maskedKey = substr($this->apiKey, 0, 5) . '***';
            fwrite($debugLog, "API Key: {$maskedKey}\n");
            fwrite($debugLog, "Base URL: {$this->baseUrl}\n");
            fwrite($debugLog, "Protocol: " . parse_url($this->baseUrl, PHP_URL_SCHEME) . "\n");
            fwrite($debugLog, "Note: Aviationstack free tier only supports HTTP, not HTTPS\n");

            // Make basic API test request
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get("{$this->baseUrl}/flights", [
                    'access_key' => $this->apiKey,
                    'limit' => 1
                ]);

            // Log response details
            fwrite($debugLog, "Status: " . $response->status() . "\n");
            fwrite($debugLog, "Headers: " . json_encode($response->headers()) . "\n");
            fwrite($debugLog, "Body: " . substr($response->body(), 0, 1000) . "\n\n");
            fclose($debugLog);

            // Parse response
            $responseData = $response->json();

            // Check for API-specific errors
            if (isset($responseData['error'])) {
                $errorCode = $responseData['error']['code'] ?? 0;
                $errorInfo = $responseData['error']['info'] ?? 'Unknown error';
                $errorType = $responseData['error']['type'] ?? 'unknown';

                // Special message for HTTPS restriction on free plan
                if ($errorCode == 105) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'message' => 'The free tier of Aviationstack does not support HTTPS. Please use HTTP instead.',
                            'code' => $errorCode,
                            'type' => $errorType,
                            'details' => $errorInfo
                        ],
                        'current_url' => $this->baseUrl
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => $errorInfo,
                        'code' => $errorCode,
                        'type' => $errorType
                    ]
                ], 400);
            }

            // Return response to client
            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'protocol' => parse_url($this->baseUrl, PHP_URL_SCHEME),
                'api_note' => 'Free tier of Aviationstack only supports HTTP, not HTTPS',
                'response' => $responseData,
                'message' => 'Connection test completed. Check logs for details.'
            ]);
        } catch (\Exception $e) {
            // Log error
            if (isset($debugLog) && is_resource($debugLog)) {
                fwrite($debugLog, "ERROR: " . $e->getMessage() . "\n");
                fwrite($debugLog, "Stack trace: " . $e->getTraceAsString() . "\n\n");
                fclose($debugLog);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
