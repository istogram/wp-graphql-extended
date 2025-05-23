<?php

namespace Istogram\GraphQLExtended\GraphQL;

class PaginationExtension {
    public function __construct() {
        add_action('graphql_register_types', [$this, 'registerFields']);
        add_filter('graphql_PostObjectsConnectionOrderbyEnum_values', [$this, 'addOffsetToOrderBy']);
        add_filter('graphql_input_fields', [$this, 'addOffsetPaginationArg'], 10, 2);
        add_filter('graphql_post_object_connection_query_args', [$this, 'handleOffsetPagination'], 10, 3);
        add_filter('posts_request', [$this, 'captureTermId'], 10, 2);

        if ($this->isDebugEnabled()) {
            add_filter('posts_request', [$this, 'logSQLQuery'], 10, 2);
            add_action('graphql_return_response', [$this, 'logResponse']);
        }
    }

    public function captureTermId($sql, $query) {
        if (defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST) {
            if (preg_match('/term_taxonomy_id IN \((\d+)\)/', $sql, $matches)) {
                $this->current_term_id = (int) $matches[1];
            }
        }
        return $sql;
    }

    public function registerFields() {
        $this->logDebug('Registering total field for pagination');

        // Register OffsetPagination type
        register_graphql_object_type('OffsetPagination', [
            'fields' => [
                'total' => [
                    'type' => 'Int',
                    'description' => 'Total number of items'
                ]
            ]
        ]);
        
        // Register for posts
        register_graphql_field('RootQueryToPostConnectionPageInfo', 'total', [
            'type' => 'Int',
            'description' => 'Total number of posts',
            'resolve' => function($page_info) {
                $total = wp_count_posts('post')->publish;
                $this->logDebug('Resolving total posts count', ['total' => $total]);
                return $total;
            }
        ]);


        // Register for category posts
        register_graphql_field('CategoryToPostConnectionPageInfo', 'offsetPagination', [
            'type' => 'OffsetPagination',
            'description' => 'Offset pagination information',
            'resolve' => function($page_info, $args, $context, $info) {
                if ($this->current_term_id) {
                    global $wpdb;
                    
                    // Get term_id from term_taxonomy table
                    $term_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d",
                        $this->current_term_id
                    ));
                    
                    if ($term_id) {
                        // Count posts for this term
                        $count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(DISTINCT p.ID) 
                            FROM {$wpdb->posts} p 
                            JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
                            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                            WHERE tt.term_id = %d 
                            AND p.post_type = 'post' 
                            AND p.post_status = 'publish'",
                            $term_id
                        ));

                        return [
                            'total' => (int) $count
                        ];
                    }
                }

                return [
                    'total' => 0
                ];
            }
        ]);

        // Register for tag posts
        register_graphql_field('TagToPostConnectionPageInfo', 'offsetPagination', [
            'type' => 'OffsetPagination',
            'description' => 'Offset pagination information',
            'resolve' => function($page_info, $args, $context, $info) {
                if($this->current_term_id) {
                    $count = get_term($this->current_term_id, 'post_tag')->count;
                    return [
                        'total' => $count
                    ];
                }

                return [
                    'total' => 0
                ];
            }
        ]);
    }

    public function addOffsetToOrderBy($values) {
        $this->logDebug('Adding OFFSET to orderby enum values');
        
        if (!isset($values['OFFSET'])) {
            $values['OFFSET'] = [
                'value' => 'offset',
                'description' => __('Order by offset', 'wp-graphql-extended'),
            ];
        }
        return $values;
    }

    public function addOffsetPaginationArg($fields, $type_name) {
        if ($type_name === 'RootQueryToPostConnectionWhereArgs' || 
            $type_name === 'CategoryToPostConnectionWhereArgs' || 
            $type_name === 'TagToPostConnectionWhereArgs') {
            $this->logDebug('Adding offsetPagination to where args', [
                'type_name' => $type_name
            ]);
            
            $fields['offsetPagination'] = [
                'type' => ['list_of' => 'Int'],
                'description' => __('Paginate by offset', 'wp-graphql-extended'),
            ];
        }
        return $fields;
    }

    public function handleOffsetPagination($query_args, $source, $args) {
        $this->logDebug('Processing query args', [
            'original_args' => $args,
            'where_args' => $args['where'] ?? 'none'
        ]);

        if (isset($args['where']['offsetPagination']) && is_array($args['where']['offsetPagination'])) {
            [$offset, $per_page] = $args['where']['offsetPagination'];
            
            $query_args['offset'] = $offset;
            $query_args['posts_per_page'] = $per_page;
            
            $this->logDebug('Applied offset pagination', [
                'offset' => $offset,
                'posts_per_page' => $per_page,
                'final_query_args' => $query_args
            ]);
        }
        
        return $query_args;
    }

    public function logSQLQuery($sql, $query) {
        if (defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST) {
            $this->logDebug('WordPress SQL Query', [
                'sql' => $sql,
                'post_type' => $query->get('post_type'),
                'offset' => $query->get('offset'),
                'posts_per_page' => $query->get('posts_per_page')
            ]);
        }
        return $sql;
    }

    public function logResponse($response) {
        if (isset($response['data']['posts'])) {
            $this->logDebug('GraphQL Posts Response', [
                'total_nodes' => count($response['data']['posts']['nodes']),
                'page_info' => $response['data']['posts']['pageInfo'] ?? 'none'
            ]);
        }
    }

    private function logDebug($message, array $data = null) {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[GraphQL Pagination $timestamp] $message";
        
        if ($data !== null) {
            $log_message .= "\nData: " . print_r($data, true);
        }
        
        error_log($log_message);
    }

    private function isDebugEnabled(): bool {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}