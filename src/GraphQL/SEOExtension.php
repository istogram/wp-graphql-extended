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
        // Twitter Card Type - Fix image field
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
                    'type' => 'String', // Changed from SEOImage to String
                    'description' => 'Twitter card image URL'
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

        // Add SEO field to Page type
        register_graphql_field('Page', 'seo', [
            'type' => 'SEO',
            'description' => 'SEO data from The SEO Framework',
            'resolve' => [$this, 'resolveSEOField']
        ]);

        // Add SEO field to Category type
        register_graphql_field('Category', 'seo', [
            'type' => 'SEO',
            'description' => 'SEO data from The SEO Framework',
            'resolve' => [$this, 'resolveCategorySEOField']
        ]);

        // Add SEO field to Tag type
        register_graphql_field('Tag', 'seo', [
            'type' => 'SEO',
            'description' => 'SEO data from The SEO Framework',
            'resolve' => [$this, 'resolveTagSEOField']
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

    public function resolveCategorySEOField($category): ?array {
        // Get The SEO Framework instance
        $tsf = \the_seo_framework();
        
        if (!$tsf || !isset($category->term_id)) {
            $this->logDebug('Unable to resolve Category SEO field', [
                'tsf_exists' => (bool)$tsf,
                'category' => $category,
                'term_id' => $category->term_id ?? null
            ]);
            return null;
        }

        try {
            $term_id = $category->term_id;
            
            // Get the term meta
            $term_meta = get_term_meta($term_id);
            
            // Build title and description with fallbacks
            $title = $category->name;
            $description = $category->description;

            // Get robots meta using WP's built-in functions as fallback
            $robots = [];
            if (is_array($term_meta) && isset($term_meta['_tsf_robots'][0])) {
                $robots = maybe_unserialize($term_meta['_tsf_robots'][0]);
            }
            
            // Get canonical URL
            $canonical_url = get_term_link($term_id);
            if (is_wp_error($canonical_url)) {
                $canonical_url = '';
            }

            // Get social image
            $social_image_url = '';
            if (is_array($term_meta) && isset($term_meta['_tsf_social_image_url'][0])) {
                $social_image_url = $term_meta['_tsf_social_image_url'][0];
            }

            $social_image_url = $social_image_url ?: null; // Set to null if empty

            $seo_data = [
                'title' => wp_strip_all_tags($title),
                'description' => wp_strip_all_tags($description),
                'canonicalUrl' => $canonical_url,
                'robots' => is_array($robots) ? implode(',', $robots) : '',
                'openGraph' => [
                    'title' => wp_strip_all_tags($title),
                    'description' => wp_strip_all_tags($description),
                    'image' => [
                        'url' => $social_image_url
                    ],
                    'type' => 'website',
                    'modifiedTime' => current_time('c')
                ],
                'twitter' => [
                    'title' => wp_strip_all_tags($title),
                    'description' => wp_strip_all_tags($description),
                    'image' => $social_image_url, // Just pass the URL string directly
                    'cardType' => 'summary_large_image'
                ],
                'schema' => [
                    'articleType' => 'CollectionPage',
                    'pageType' => 'CollectionPage'
                ]
            ];

            // Try to get SEO Framework specific meta if available
            if (method_exists($tsf->data()->plugin()->term(), 'get_meta')) {
                $meta = $tsf->data()->plugin()->term()->get_meta($term_id);
                
                if (is_array($meta)) {
                    if (!empty($meta['title'])) {
                        $seo_data['title'] = $meta['title'];
                        $seo_data['openGraph']['title'] = $meta['og_title'] ?: $meta['title'];
                        $seo_data['twitter']['title'] = $meta['twitter_title'] ?: $meta['title'];
                    }
                    
                    if (!empty($meta['description'])) {
                        $seo_data['description'] = $meta['description'];
                        $seo_data['openGraph']['description'] = $meta['og_description'] ?: $meta['description'];
                        $seo_data['twitter']['description'] = $meta['twitter_description'] ?: $meta['description'];
                    }
                }
            }

            $this->logDebug('Generated Category SEO data', [
                'term_id' => $term_id,
                'data' => $seo_data
            ]);

            return $seo_data;
        } catch (\Exception $e) {
            $this->logDebug('Error generating Category SEO data', [
                'term_id' => $category->term_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function resolveTagSEOField($tag): ?array {
        // Get The SEO Framework instance
        $tsf = \the_seo_framework();
        
        if (!$tsf || !isset($tag->term_id)) {
            $this->logDebug('Unable to resolve Tag SEO field', [
                'tsf_exists' => (bool)$tsf,
                'tag' => $tag,
                'term_id' => $tag->term_id ?? null
            ]);
            return null;
        }

        try {
            $term_id = $tag->term_id;
            
            // Get the term meta
            $term_meta = get_term_meta($term_id);
            
            // Build title and description with fallbacks
            $title = $tag->name;
            $description = $tag->description;

            // Get robots meta using WP's built-in functions as fallback
            $robots = [];
            if (is_array($term_meta) && isset($term_meta['_tsf_robots'][0])) {
                $robots = maybe_unserialize($term_meta['_tsf_robots'][0]);
            }
            
            // Get canonical URL - Note the difference here: using tag-specific function
            $canonical_url = get_tag_link($term_id);
            if (is_wp_error($canonical_url)) {
                $canonical_url = '';
            }

            // Get social image
            $social_image_url = '';
            if (is_array($term_meta) && isset($term_meta['_tsf_social_image_url'][0])) {
                $social_image_url = $term_meta['_tsf_social_image_url'][0];
            }

            $social_image_url = $social_image_url ?: null;

            $seo_data = [
                'title' => wp_strip_all_tags($title),
                'description' => wp_strip_all_tags($description),
                'canonicalUrl' => $canonical_url,
                'robots' => is_array($robots) ? implode(',', $robots) : '',
                'openGraph' => [
                    'title' => wp_strip_all_tags($title),
                    'description' => wp_strip_all_tags($description),
                    'image' => [
                        'url' => $social_image_url
                    ],
                    'type' => 'website',
                    'modifiedTime' => current_time('c')
                ],
                'twitter' => [
                    'title' => wp_strip_all_tags($title),
                    'description' => wp_strip_all_tags($description),
                    'image' => $social_image_url,
                    'cardType' => 'summary_large_image'
                ],
                'schema' => [
                    'articleType' => 'CollectionPage',
                    'pageType' => 'CollectionPage'
                ]
            ];

            // Try to get SEO Framework specific meta if available
            if (method_exists($tsf->data()->plugin()->term(), 'get_meta')) {
                $meta = $tsf->data()->plugin()->term()->get_meta($term_id);
                
                if (is_array($meta)) {
                    if (!empty($meta['title'])) {
                        $seo_data['title'] = $meta['title'];
                        $seo_data['openGraph']['title'] = $meta['og_title'] ?: $meta['title'];
                        $seo_data['twitter']['title'] = $meta['twitter_title'] ?: $meta['title'];
                    }
                    
                    if (!empty($meta['description'])) {
                        $seo_data['description'] = $meta['description'];
                        $seo_data['openGraph']['description'] = $meta['og_description'] ?: $meta['description'];
                        $seo_data['twitter']['description'] = $meta['twitter_description'] ?: $meta['description'];
                    }
                }
            }

            $this->logDebug('Generated Tag SEO data', [
                'term_id' => $term_id,
                'data' => $seo_data
            ]);

            return $seo_data;
        } catch (\Exception $e) {
            $this->logDebug('Error generating Tag SEO data', [
                'term_id' => $tag->term_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
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
