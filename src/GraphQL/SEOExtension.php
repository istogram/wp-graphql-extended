<?php

namespace Istogram\GraphQLExtended\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

class SEOExtension {
    public function __construct() {
        add_action('graphql_register_types', [$this, 'registerSEOTypes']);
        
        if ($this->isDebugEnabled()) {
            add_action('graphql_resolve_field', [$this, 'logSEOFieldResolution'], 10, 4);
        }
    }

    public function registerSEOTypes(): void {
        // Twitter Card Type
        register_graphql_object_type('SEOTwitter', [
            'description' => 'Twitter card data from The SEO Framework',
            'fields' => [
                'title' => [
                    'type' => 'String',
                    'description' => 'Twitter card title'
                ],
                'description' => [
                    'type' => 'String',
                    'description' => 'Twitter card description'
                ],
                'image' => [
                    'type' => 'SEOImage',
                    'description' => 'Twitter card image',
                    'resolve' => function($source) {
                        return ['url' => $source['image'] ?? null];
                    }
                ],
                'cardType' => [
                    'type' => 'String',
                    'description' => 'Twitter card type'
                ]
            ]
        ]);

        // SEO Image Type
        register_graphql_object_type('SEOImage', [
            'description' => 'SEO image data',
            'fields' => [
                'url' => [
                    'type' => 'String',
                    'description' => 'URL of the image'
                ]
            ]
        ]);

        // Open Graph Type
        register_graphql_object_type('SEOOpenGraph', [
            'description' => 'Open Graph data from The SEO Framework',
            'fields' => [
                'title' => [
                    'type' => 'String',
                    'description' => 'Open Graph title'
                ],
                'description' => [
                    'type' => 'String',
                    'description' => 'Open Graph description'
                ],
                'image' => [
                    'type' => 'SEOImage',
                    'description' => 'Open Graph image'
                ],
                'type' => [
                    'type' => 'String',
                    'description' => 'Open Graph type'
                ],
                'modifiedTime' => [
                    'type' => 'String',
                    'description' => 'Last modified time'
                ]
            ]
        ]);

        // Schema Type
        register_graphql_object_type('SEOSchema', [
            'description' => 'Schema data from The SEO Framework',
            'fields' => [
                'articleType' => [
                    'type' => 'String',
                    'description' => 'Article schema type'
                ],
                'pageType' => [
                    'type' => 'String',
                    'description' => 'Page schema type'
                ]
            ]
        ]);

        // Main SEO Type
        register_graphql_object_type('SEO', [
            'description' => 'SEO data from The SEO Framework',
            'fields' => [
                'title' => [
                    'type' => 'String',
                    'description' => 'SEO title'
                ],
                'description' => [
                    'type' => 'String',
                    'description' => 'SEO description'
                ],
                'canonicalUrl' => [
                    'type' => 'String',
                    'description' => 'Canonical URL'
                ],
                'robots' => [
                    'type' => 'String',
                    'description' => 'Robots meta directives'
                ],
                'openGraph' => [
                    'type' => 'SEOOpenGraph',
                    'description' => 'Open Graph data'
                ],
                'twitter' => [
                    'type' => 'SEOTwitter',
                    'description' => 'Twitter card data'
                ],
                'schema' => [
                    'type' => 'SEOSchema',
                    'description' => 'Schema.org data'
                ]
            ]
        ]);

        // Add SEO field to Post type
        register_graphql_field('Post', 'seo', [
            'type' => 'SEO',
            'description' => 'SEO data from The SEO Framework',
            'resolve' => [$this, 'resolveSEOField']
        ]);
    }

    /**
     * Log SEO field resolution
     * 
     * @param mixed $value The value being filtered
     * @param mixed $source The source passed down the Resolve Tree
     * @param array $args Array of arguments input in the field as part of the GraphQL query
     * @param AppContext $context Object containing app context that gets passed down the resolve tree
     * @param ResolveInfo|null $info Info about fields passed down the resolve tree
     * @param string|null $type_name The name of the type the fields belong to
     * @param string|null $field_key The name of the field
     * @param array|null $field The field config
     * @param mixed|null $field_resolver The default field resolver
     * @return mixed
     */
    public function logSEOFieldResolution($value, $source = null, $args = [], $context = null, $info = null, $type_name = '', $field_key = '', $field = null, $field_resolver = null) {
        try {
            if ($field_key === 'seo') {
                $this->logDebug('SEO field resolution', [
                    'field' => $field_key,
                    'type' => $type_name,
                    'post_id' => $source->ID ?? null,
                    'args' => $args
                ]);
            }
        } catch (\Exception $e) {
            $this->logDebug('Error in SEO field resolution logging', [
                'error' => $e->getMessage()
            ]);
        }
        return $value;
    }

    public function resolveSEOField($post): ?array {
        // Get The SEO Framework instance
        $tsf = \the_seo_framework();
        
        if (!$tsf || !isset($post->ID)) {
            $this->logDebug('Unable to resolve SEO field', [
                'tsf_exists' => (bool)$tsf,
                'post' => $post,
                'post_id' => $post->ID ?? null
            ]);
            return null;
        }

        // Set up the query ID
        $tsf->query_cache['id'] = $post->ID;

        // Get the meta values
        $seo_meta = get_post_meta($post->ID, '_genesis_title', true);
        $seo_desc = get_post_meta($post->ID, '_genesis_description', true);
        
        // Get titles with fallback
        $title = !empty($seo_meta) ? $seo_meta : $tsf->get_title($post->ID);
        $description = !empty($seo_desc) ? $seo_desc : $tsf->get_description($post->ID);

        $this->logDebug('Resolving SEO field', [
            'post_id' => $post->ID,
            'raw_title' => $seo_meta,
            'raw_description' => $seo_desc,
            'final_title' => $title,
            'final_description' => $description
        ]);

        try {
            $seo_data = [
                'title' => $title,
                'description' => $description,
                'canonicalUrl' => $tsf->get_canonical_url($post->ID),
                'robots' => implode(',', $tsf->get_robots_meta($post->ID)),
                'openGraph' => [
                    'title' => $tsf->get_open_graph_title($post->ID),
                    'description' => $tsf->get_open_graph_description($post->ID),
                    'image' => [
                        'url' => $tsf->get_open_graph_image_url($post->ID)
                    ],
                    'type' => $tsf->get_open_graph_type($post->ID),
                    'modifiedTime' => get_the_modified_time('c', $post->ID)
                ],
                'twitter' => [
                    'title' => $tsf->get_twitter_title($post->ID),
                    'description' => $tsf->get_twitter_description($post->ID),
                    'image' => $tsf->get_twitter_image_url($post->ID),
                    'cardType' => $tsf->get_twitter_card_type($post->ID)
                ],
                'schema' => [
                    'articleType' => 'Article',
                    'pageType' => 'Article'
                ]
            ];

            $this->logDebug('Generated SEO data', [
                'post_id' => $post->ID,
                'data' => $seo_data
            ]);

            return $seo_data;
        } catch (\Exception $e) {
            $this->logDebug('Error generating SEO data', [
                'post_id' => $post->ID,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function logDebug(string $message, array $context = []): void {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $log_message = '[GraphQL SEO] ' . $message;
        if (!empty($context)) {
            $log_message .= ' Context: ' . print_r($context, true);
        }

        error_log($log_message);
    }

    private function isDebugEnabled(): bool {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}
