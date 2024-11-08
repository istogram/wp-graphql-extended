<?php

namespace Istogram\GraphQLExtended\Authentication;

use function Env\env;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class AuthTest {
    private $route = 'auth-test';

    public function __construct() {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_filter('rest_authentication_errors', [$this, 'handleRestAuth'], 99);
    }

    public function handleRestAuth($result) {
        $current_route = trim(str_replace(rest_get_url_prefix(), '', $_SERVER['REQUEST_URI']), '/');
        $test_route = trim(RestConfiguration::getNamespace() . '/' . $this->route, '/');
        
        if (strpos($current_route, $test_route) === 0) {
            return true;
        }

        return $result;
    }

    public function registerRoutes() {
        $namespace = RestConfiguration::getNamespace();

        register_rest_route($namespace, $this->route . '/info', [
            'methods' => 'GET',
            'callback' => [$this, 'getTokenInfo'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($namespace, $this->route . '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generateTestToken'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);

        register_rest_route($namespace, $this->route . '/decode', [
            'methods' => 'POST',
            'callback' => [$this, 'decodeToken'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);
    }

    public function generateTestToken($request) {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new \WP_Error('invalid_credentials', 'Invalid credentials', ['status' => 401]);
        }

        $secret_key = $this->getSecretKey();
        if (empty($secret_key)) {
            return new \WP_Error('jwt_auth_bad_config', 'JWT is not configured properly', ['status' => 500]);
        }

        $issued = time();
        $expiration = $issued + JWTConfiguration::AUTH_TOKEN_EXPIRATION;

        $token_data = [
            'iss' => get_bloginfo('url'),
            'iat' => $issued,
            'nbf' => $issued,
            'exp' => $expiration,
            'data' => [
                'user' => [
                    'id' => $user->ID,
                ]
            ]
        ];

        try {
            $token = JWT::encode($token_data, $secret_key, 'HS256');
            return [
                'token' => $token,
                'expires' => date('Y-m-d H:i:s', $expiration),
                'issued' => date('Y-m-d H:i:s', $issued),
                'user_id' => $user->ID,
                'user_email' => $user->user_email,
                'user_display_name' => $user->display_name
            ];
        } catch (\Exception $e) {
            return new \WP_Error('jwt_auth_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function decodeToken($request) {
        $token = $request->get_param('token');
        $secret_key = $this->getSecretKey();

        if (empty($token)) {
            return new \WP_Error('jwt_auth_invalid', 'Token is required', ['status' => 400]);
        }

        try {
            $token_parts = explode('.', $token);
            if (count($token_parts) != 3) {
                return new \WP_Error('jwt_auth_invalid', 'Invalid token format', ['status' => 401]);
            }

            $payload = json_decode(base64_decode(str_replace(
                ['-', '_'],
                ['+', '/'],
                $token_parts[1]
            )));

            if (!$payload) {
                return new \WP_Error('jwt_auth_invalid', 'Invalid token payload', ['status' => 401]);
            }

            $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));

            return [
                'payload' => $payload,
                'valid' => true,
                'expires' => date('Y-m-d H:i:s', $payload->exp),
                'issued' => date('Y-m-d H:i:s', $payload->iat),
                'time_to_expiration' => ($payload->exp - time()) . ' seconds',
                'user_id' => $payload->data->user->id,
                'decoded_token' => $decoded,
                'issuer' => $payload->iss
            ];
        } catch (\Exception $e) {
            return new \WP_Error(
                'jwt_auth_invalid',
                $e->getMessage(),
                [
                    'status' => 401,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ]
            );
        }
    }

    public function getTokenInfo() {
        return [
            'environment' => env('WP_ENV'),
            'secret_key_configured' => !empty($this->getSecretKey()),
            'cors_enabled' => defined('GRAPHQL_JWT_AUTH_CORS_ENABLE') && GRAPHQL_JWT_AUTH_CORS_ENABLE,
            'allowed_origins' => $this->getAllowedOrigins(),
            'graphql_endpoint' => get_bloginfo('url') . '/graphql',
            'current_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'auth_token_expiration' => JWTConfiguration::AUTH_TOKEN_EXPIRATION . ' seconds',
            'refresh_token_expiration' => JWTConfiguration::REFRESH_TOKEN_EXPIRATION . ' seconds'
        ];
    }

    /**
     * Get the secret key for JWT authentication
     *
     * @return string
     */
    private function getSecretKey(): string {
        if (defined('GRAPHQL_JWT_AUTH_SECRET_KEY')) {
            return GRAPHQL_JWT_AUTH_SECRET_KEY;
        }

        return env('GRAPHQL_JWT_AUTH_SECRET_KEY', '');
    }

    /**
     * Get allowed origins for CORS
     *
     * @return array
     */
    private function getAllowedOrigins(): array {
        $origins = env('GRAPHQL_JWT_AUTH_ALLOWED_ORIGINS', '');
        if (empty($origins)) {
            return ['*'];
        }

        return array_map('trim', explode(',', $origins));
    }

    /**
     * Log debug messages if debug mode is enabled
     *
     * @param string $message
     * @param array|null $data
     */
    private function logDebug(string $message, ?array $data = null): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = '[JWT Auth Test] ' . $message;
        if ($data !== null) {
            $log_message .= ' Data: ' . print_r($data, true);
        }

        error_log($log_message);
    }
}