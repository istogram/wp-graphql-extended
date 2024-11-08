<?php

namespace Istogram\GraphQLExtended\Authentication;

use function Env\env;

class JWTConfiguration {
    // Constants for token expiration
    const AUTH_TOKEN_EXPIRATION = 60 * 60; // 1 hour
    const REFRESH_TOKEN_EXPIRATION = 60 * 60 * 24 * 30; // 30 days

    public function __construct() {
        $this->defineConstants();
        $this->initHooks();
    }

    private function defineConstants() {
        if (!defined('GRAPHQL_JWT_AUTH_SECRET_KEY')) {
            define('GRAPHQL_JWT_AUTH_SECRET_KEY', env('GRAPHQL_JWT_AUTH_SECRET_KEY'));
        }

        if (!defined('GRAPHQL_JWT_AUTH_CORS_ENABLE')) {
            define('GRAPHQL_JWT_AUTH_CORS_ENABLE', true);
        }
    }

    private function initHooks() {
        add_filter('graphql_jwt_auth_expire', [$this, 'setAuthTokenExpiration'], 99);
        add_filter('graphql_refresh_token_expire', [$this, 'setRefreshTokenExpiration'], 99);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_filter('graphql_jwt_auth_token_before_sign', [$this, 'logTokenData'], 10, 2);
            add_filter('graphql_refresh_token_before_sign', [$this, 'logRefreshTokenData'], 10, 2);
        }
    }

    public function setAuthTokenExpiration() {
        return time() + self::AUTH_TOKEN_EXPIRATION;
    }

    public function setRefreshTokenExpiration() {
        return time() + self::REFRESH_TOKEN_EXPIRATION;
    }

    public function logTokenData($token, $user) {
        error_log('JWT Auth Token Data for user ' . $user->ID . ': ' . print_r($token, true));
        return $token;
    }

    public function logRefreshTokenData($token, $user) {
        error_log('JWT Refresh Token Data for user ' . $user->ID . ': ' . print_r($token, true));
        return $token;
    }
}