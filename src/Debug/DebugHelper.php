<?php

namespace Istogram\GraphQLExtended\Debug;

class DebugHelper {
    public function __construct() {
        $this->initHooks();
    }

    private function initHooks() {
        add_action('graphql_register_types', [$this, 'logTypeRegistration']);
        add_action('graphql_execute', [$this, 'logQueryExecution'], 10, 4);
    }

    public function logTypeRegistration() {
        $this->log('Registering GraphQL types');
    }

    public function logQueryExecution($response, $schema, $operation, $query) {
        $this->log('Executing GraphQL query', [
            'operation' => $operation,
            'query' => $query,
            'response' => $response
        ]);
    }

    public function log($message, array $context = []) {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[GraphQL Debug $timestamp] $message";
        
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($log_message);
        }

        if (!empty($context)) {
            error_log('Context: ' . print_r($context, true));
        }
    }

    private function isDebugEnabled(): bool {
        return defined('WP_DEBUG') && WP_DEBUG && defined('GRAPHQL_DEBUG') && GRAPHQL_DEBUG;
    }
}