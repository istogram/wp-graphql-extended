<?php

namespace Istogram\GraphQLExtended\Authentication;

class RestConfiguration {
    /**
     * API namespace for the plugin
     */
    private const API_NAMESPACE = 'wp-graphql-extended/v1';

    public function __construct() {
        add_filter('rest_authentication_errors', [$this, 'handleRestAuthentication'], 99);
    }

    /**
     * Handle REST API authentication
     *
     * @param mixed $result Current authentication status
     * @return true|WP_Error True if authentication succeeded, WP_Error if failed
     */
    public function handleRestAuthentication($result) {
        // If a previous authentication check was applied,
        // pass that result along without modification
        if (true === $result || is_wp_error($result)) {
            return $result;
        }

        // Allow access to the test endpoints
        if (strpos($_SERVER['REQUEST_URI'], self::API_NAMESPACE . '/auth-test') !== false) {
            return true;
        }

        // No authentication has been performed yet
        // Return an error if user is not logged in
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'rest_not_logged_in',
                __('You are not currently logged in.', 'wp-graphql-extended'),
                ['status' => 401]
            );
        }

        // If we get here, the user is authenticated
        return true;
    }

    /**
     * Get the API namespace
     *
     * @return string
     */
    public static function getNamespace(): string {
        return self::API_NAMESPACE;
    }
}