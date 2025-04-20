<?php

namespace Istogram\GraphQLExtended\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

class SEOExtension
{
    public function __construct()
    {
        add_action('graphql_register_types', [$this, 'registerSEOTypes']);

        // Add settings for the frontend URL
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);

        if ($this->isDebugEnabled()) {
            add_action('graphql_resolve_field', [$this, 'logSEOFieldResolution'], 10, 4);
        }
    }


    public function registerSEOTypes(): void
    {
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
     * Get the frontend URL for headless setup
     *
     * @return string The frontend URL without trailing slash
     */
    private function getFrontendUrl(): string
    {
        // Check for specific option first
        $frontend_url = get_option('graphql_seo_frontend_url', '');

        // If not set, try common options that might be set by other plugins
        if (empty($frontend_url)) {
            $frontend_url = get_option('istogram_frontend_url', '');
        }

        // If still not set, try others or use constants
        if (empty($frontend_url)) {
            // Check if a constant is defined (could be defined in wp-config.php)
            if (defined('FRONTEND_URL')) {
                $frontend_url = FRONTEND_URL;
            } elseif (defined('HEADLESS_FRONTEND_URL')) {
                $frontend_url = HEADLESS_FRONTEND_URL;
            }
        }

        // If still empty, fallback to the WordPress home URL
        if (empty($frontend_url)) {
            $frontend_url = home_url();
        }

        // Remove trailing slash
        return untrailingslashit($frontend_url);
    }

    /**
     * Build a canonical URL for a post that matches the frontend URL structure
     *
     * @param int $post_id The post ID
     * @return string The canonical URL
     */
    private function buildCanonicalUrl($post_id): string
    {
        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            $this->logDebug('Cannot build canonical URL - post not found', ['post_id' => $post_id]);
            return '';
        }

        // Get frontend URL
        $frontend_url = $this->getFrontendUrl();

        // Get post info
        $post_type = get_post_type($post);
        $slug = $post->post_name;

        $this->logDebug('Building canonical URL', [
            'post_id' => $post_id,
            'post_type' => $post_type,
            'slug' => $slug,
            'frontend_url' => $frontend_url
        ]);

        // Build URL based on post type
        if ($post_type === 'post') {
            return $frontend_url . '/blog/' . $slug;
        } elseif ($post_type === 'page') {
            // Handle home page
            if ($post->ID === get_option('page_on_front')) {
                return $frontend_url;
            }

            // Regular pages
            return $frontend_url . '/' . $slug;
        }

        // For custom post types, use post_type/slug pattern
        return $frontend_url . '/' . $post_type . '/' . $slug;
    }

    /**
     * Build a canonical URL for a term that matches the frontend URL structure
     *
     * @param int $term_id The term ID
     * @param string $taxonomy The taxonomy (category, post_tag, etc.)
     * @return string The canonical URL
     */
    private function buildTermCanonicalUrl($term_id, $taxonomy): string
    {
        // Get the term
        $term = get_term($term_id, $taxonomy);
        if (is_wp_error($term) || !$term) {
            $this->logDebug('Cannot build term canonical URL - term not found', [
                'term_id' => $term_id,
                'taxonomy' => $taxonomy
            ]);
            return '';
        }

        // Get frontend URL
        $frontend_url = $this->getFrontendUrl();

        // Get term slug
        $slug = $term->slug;

        $this->logDebug('Building term canonical URL', [
            'term_id' => $term_id,
            'taxonomy' => $taxonomy,
            'slug' => $slug,
            'frontend_url' => $frontend_url
        ]);

        // Build URL based on taxonomy
        if ($taxonomy === 'category') {
            return $frontend_url . '/category/' . $slug;
        } elseif ($taxonomy === 'post_tag') {
            return $frontend_url . '/tag/' . $slug;
        }

        // For custom taxonomies
        return $frontend_url . '/' . $taxonomy . '/' . $slug;
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
    public function logSEOFieldResolution($value, $source = null, $args = [], $context = null, $info = null, $type_name = '', $field_key = '', $field = null, $field_resolver = null)
    {
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

    /**
     * Resolve SEO field for categories with proper canonical URL handling
     *
     * @param object $category The category term object
     * @return array|null The SEO data
     */
    public function resolveCategorySEOField($category): ?array
    {
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

            // Get original canonical URL
            $original_canonical = get_term_link($term_id);
            if (is_wp_error($original_canonical)) {
                $original_canonical = '';
            }

            // Build our own canonical URL
            $canonical_url = $this->buildTermCanonicalUrl($term_id, 'category');

            $this->logDebug('Category canonical URL', [
                'term_id' => $term_id,
                'original_canonical' => $original_canonical,
                'new_canonical' => $canonical_url
            ]);

            // Use our canonical URL if successfully built, otherwise fall back to original
            if (empty($canonical_url)) {
                $canonical_url = $original_canonical;
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

    /**
     * Resolve SEO field for tags with proper canonical URL handling
     *
     * @param object $tag The tag term object
     * @return array|null The SEO data
     */
    public function resolveTagSEOField($tag): ?array
    {
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

            // Get original canonical URL
            $original_canonical = get_tag_link($term_id);
            if (is_wp_error($original_canonical)) {
                $original_canonical = '';
            }

            // Build our own canonical URL
            $canonical_url = $this->buildTermCanonicalUrl($term_id, 'post_tag');

            $this->logDebug('Tag canonical URL', [
                'term_id' => $term_id,
                'original_canonical' => $original_canonical,
                'new_canonical' => $canonical_url
            ]);

            // Use our canonical URL if successfully built, otherwise fall back to original
            if (empty($canonical_url)) {
                $canonical_url = $original_canonical;
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

    /**
     * Resolve SEO field for posts with proper canonical URL handling
     *
     * @param object $post The post object
     * @return array|null The SEO data
     */
    public function resolveSEOField($post): ?array
    {
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
            // Get original canonical URL from SEO Framework
            $original_canonical = $tsf->get_canonical_url($post->ID);

            // Build our own canonical URL that matches frontend structure
            $canonical_url = $this->buildCanonicalUrl($post->ID);

            $this->logDebug('Canonical URL generation', [
                'post_id' => $post->ID,
                'original_canonical' => $original_canonical,
                'new_canonical' => $canonical_url
            ]);

            // Use our canonical URL if successfully built, otherwise fall back to original
            if (empty($canonical_url)) {
                $canonical_url = $original_canonical;
            }

            $seo_data = [
                'title' => $title,
                'description' => $description,
                'canonicalUrl' => $canonical_url,
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

    private function logDebug(string $message, array $context = []): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $log_message = '[GraphQL SEO] ' . $message;
        if (!empty($context)) {
            $log_message .= ' Context: ' . print_r($context, true);
        }

        error_log($log_message);
    }

    private function isDebugEnabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }


    /**
     * Add settings page
     */
    public function addSettingsPage()
    {
        add_options_page(
            'GraphQL SEO Settings',
            'GraphQL SEO',
            'manage_options',
            'graphql-seo-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Register settings
     */
    public function registerSettings()
    {
        register_setting('graphql_seo_settings', 'graphql_seo_frontend_url');

        add_settings_section(
            'graphql_seo_main_section',
            'Frontend URL Settings',
            [$this, 'settingsSectionCallback'],
            'graphql-seo-settings'
        );

        add_settings_field(
            'graphql_seo_frontend_url',
            'Frontend URL',
            [$this, 'frontendUrlCallback'],
            'graphql-seo-settings',
            'graphql_seo_main_section'
        );
    }

    /**
     * Settings section description
     */
    public function settingsSectionCallback()
    {
        echo '<p>Configure settings for the GraphQL SEO integration.</p>';
    }

    /**
     * Frontend URL field callback
     */
    public function frontendUrlCallback()
    {
        $frontend_url = get_option('graphql_seo_frontend_url', '');
        echo '<input type="url" name="graphql_seo_frontend_url" value="' . esc_attr($frontend_url) . '" class="regular-text">';
        echo '<p class="description">Enter the URL of your frontend site (e.g., https://www.yourdomain.com)</p>';
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage()
    {
        ?>
    <div class="wrap">
        <h1>GraphQL SEO Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('graphql_seo_settings');
        do_settings_sections('graphql-seo-settings');
        submit_button();
        ?>
        </form>
    </div>
    <?php
    }
}
