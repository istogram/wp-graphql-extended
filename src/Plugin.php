<?php

namespace Istogram\GraphQLExtended;

class Plugin {
    public function __construct() {
        // Initialize components
        $this->initializeComponents();
        
        // Set GraphQL constants
        $this->defineConstants();
    }

    private function initializeComponents() {
        // Initialize JWT Configuration
        new Authentication\JWTConfiguration();
        
        // Initialize GraphQL Setup
        new GraphQL\Setup();
        
        // Initialize Pagination Extension
        new GraphQL\PaginationExtension();
        
        // Initialize SEO Extension
        new GraphQL\SEOExtension();
        
        // Initialize Debug Helper in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            new Debug\DebugHelper();
        }
        
        // Initialize Auth Test endpoints if in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            new Authentication\AuthTest();
        }
    }

    private function defineConstants() {
        if (!defined('GRAPHQL_DEBUG')) {
            define('GRAPHQL_DEBUG', WP_DEBUG);
        }

        if (!defined('GRAPHQL_REQUEST')) {
            define('GRAPHQL_REQUEST', strpos($_SERVER['REQUEST_URI'], '/graphql') !== false);
        }
    }
}