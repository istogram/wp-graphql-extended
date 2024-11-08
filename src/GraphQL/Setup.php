<?php

namespace Istogram\GraphQLExtended\GraphQL;

use function Env\env;

class Setup {
    public function __construct() {
        add_action('init', [$this, 'handleRequest'], 0);
        
        if ($this->isDebugEnabled()) {
            add_filter('graphql_debug_enabled', '__return_true');
            add_action('graphql_execute', [$this, 'logFailedQueries'], 10, 4);
        }
    }

    public function handleRequest() {
        if (!$this->isGraphQLRequest()) {
            return;
        }

        $this->setHeaders();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePostRequest();
        }
    }

    private function handlePostRequest() {
        $raw_data = file_get_contents('php://input');
        $data = json_decode($raw_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendResponse([
                'errors' => [
                    ['message' => 'Invalid JSON payload']
                ]
            ]);
        }

        $query = $data['query'] ?? null;
        $variables = $data['variables'] ?? null;
        $operation = $data['operationName'] ?? null;

        if (empty($query)) {
            $this->sendResponse([
                'errors' => [
                    ['message' => 'Query is required']
                ]
            ]);
        }

        try {
            $result = graphql([
                'query' => $query,
                'variables' => $variables,
                'operation' => $operation,
            ]);
            $this->sendResponse($result);
        } catch (\Exception $e) {
            $this->sendResponse([
                'errors' => [
                    [
                        'message' => $e->getMessage(),
                        'locations' => [],
                        'path' => [],
                    ]
                ]
            ]);
        }
    }

    private function setHeaders() {
        if (headers_sent()) {
            return;
        }

        header('Content-Type: application/json; charset=UTF-8');
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed_origins = env('GRAPHQL_JWT_AUTH_ALLOWED_ORIGINS', '');
        
        if (!empty($allowed_origins)) {
            $origins = explode(',', $allowed_origins);
            if (in_array($origin, $origins)) {
                header("Access-Control-Allow-Origin: " . $origin);
            }
        } else {
            header("Access-Control-Allow-Origin: *");
        }
        
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Headers: Authorization, Content-Type");
        header("Access-Control-Max-Age: 3600");
    }

    private function sendResponse($data) {
        if (!headers_sent()) {
            $this->setHeaders();
        }
        
        echo json_encode($data);
        exit;
    }

    public function logFailedQueries($response, $schema, $operation, $query) {
        if (isset($response['errors'])) {
            error_log('GraphQL Query Failed:');
            error_log('Error: ' . print_r($response['errors'], true));
            error_log('Query: ' . $query);
        }
    }

    private function isGraphQLRequest(): bool {
        return strpos($_SERVER['REQUEST_URI'], '/graphql') !== false;
    }

    private function isDebugEnabled(): bool {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}