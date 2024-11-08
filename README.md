# WordPress GraphQL Extended

A comprehensive WordPress plugin that extends WPGraphQL functionality with JWT authentication, SEO integration, enhanced pagination, and debugging capabilities. Perfect for headless WordPress setups using frameworks like Nuxt.js.

## Features

- 🔐 **JWT Authentication**
  - Configurable token expiration
  - Refresh token support
  - Test endpoints for token generation and validation
  - CORS support for cross-origin requests

- 🎯 **SEO Integration**
  - Full The SEO Framework integration
  - Meta tags (title, description)
  - Open Graph support
  - Twitter Cards
  - Robots meta directives
  - Canonical URLs
  - Schema.org markup

- 📄 **Enhanced Pagination**
  - Offset-based pagination
  - Total count support
  - Improved page info
  - Performance optimized queries

- 🐛 **Debug Tools**
  - Detailed query logging
  - Performance monitoring
  - Request/response tracking
  - SQL query logging

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- [WPGraphQL](https://github.com/wp-graphql/wp-graphql) plugin
- [The SEO Framework](https://wordpress.org/plugins/autodescription/) plugin (for SEO features)
- Composer

## Installation

### Via Composer (Recommended)

1. Add the repository to your `composer.json`:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/istogram/wp-graphql-extended"
        }
    ]
}
```

2. Require the package:
```bash
composer require istogram/wp-graphql-extended
```

### Manual Installation

1. Download the latest release
2. Upload to your WordPress plugins directory
3. Run `composer install` in the plugin directory
4. Activate the plugin through WordPress admin

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Required
GRAPHQL_JWT_AUTH_SECRET_KEY="your-secret-key-here"

# Optional
GRAPHQL_JWT_AUTH_CORS_ENABLE=true
GRAPHQL_JWT_AUTH_ALLOWED_ORIGINS="https://your-frontend.com,https://another-domain.com"
```

### Constants (optional)

These can be defined in your `wp-config.php`:

```php
define('GRAPHQL_DEBUG', true);  // Enable GraphQL debugging
define('WP_DEBUG', true);       // Required for detailed logging
```

## Usage

### SEO Integration

Query SEO data for any post:

```graphql
query GetPostWithSEO {
  post(id: "1", idType: DATABASE_ID) {
    title
    seo {
      title
      description
      canonicalUrl
      robots
      openGraph {
        title
        description
        image {
          url
        }
        type
        modifiedTime
      }
      twitter {
        title
        description
        image {
          url
        }
        cardType
      }
      schema {
        articleType
        pageType
      }
    }
  }
}
```

Example response:
```json
{
  "data": {
    "post": {
      "title": "Sample Post",
      "seo": {
        "title": "SEO Title | Site Name",
        "description": "Meta description for search engines",
        "canonicalUrl": "https://example.com/sample-post",
        "robots": "index,follow",
        "openGraph": {
          "title": "Open Graph Title",
          "description": "Description for social sharing",
          "image": {
            "url": "https://example.com/og-image.jpg"
          }
        }
      }
    }
  }
}
```

### JWT Authentication

1. Generate a token:
```graphql
mutation LoginUser {
  login(input: {
    username: "user",
    password: "pass"
  }) {
    authToken
    refreshToken
  }
}
```

### REST API Security

The plugin includes REST API security configuration that:

- Requires authentication for all REST API endpoints except specific test routes
- Allows unauthenticated access to JWT authentication test endpoints
- Returns proper error responses for unauthorized requests

Example of protected endpoint access:
```bash
# This will fail with 401 if not authenticated
curl http://your-site/wp-json/wp/v1/posts

# This will work with a valid auth token
curl -H "Authorization: Bearer your-jwt-token" http://your-site/wp-json/wp/v1/posts

# Auth test endpoints are publicly accessible
curl http://your-site/wp-json/wp-graphql-extended/v1/auth-test/info
```

Available test endpoints:
- `GET /wp-json/wp-graphql-extended/v1/auth-test/info` - Get JWT configuration info
- `POST /wp-json/wp-graphql-extended/v1/auth-test/generate` - Generate a test token
- `POST /wp-json/wp-graphql-extended/v1/auth-test/decode` - Decode and validate a token

Configure custom endpoint access by extending the `RestConfiguration` class:

```php
add_filter('wp-graphql-extended/rest/allowed_routes', function($routes) {
    $routes[] = 'my-namespace/v1/public-endpoint';
    return $routes;
});
```

2. Use the token in your requests:
```bash
curl -H "Authorization: Bearer your-token" http://your-site/graphql
```

### Enhanced Pagination

Use offset pagination in your queries:

```graphql
query GetPosts {
  posts(
    where: {
      offsetPagination: [0, 10] # [offset, per_page]
    }
  ) {
    pageInfo {
      total
      hasNextPage
    }
    nodes {
      id
      title
      seo {
        title
        description
      }
    }
  }
}
```

### Debug Endpoints

Available when `WP_DEBUG` is true:

- Token Info: `GET /wp-json/istogram/v1/auth-test/info`
- Generate Test Token: `POST /wp-json/istogram/v1/auth-test/generate`
- Decode Token: `POST /wp-json/istogram/v1/auth-test/decode`

## Nuxt.js Integration

### Basic Setup

```javascript
// nuxt.config.js
export default {
  publicRuntimeConfig: {
    graphql: {
      url: 'http://your-wordpress/graphql',
    }
  },
  modules: [
    '@nuxtjs/apollo',
  ],
  apollo: {
    clientConfigs: {
      default: {
        httpEndpoint: 'http://your-wordpress/graphql',
        httpLinkOptions: {
          credentials: 'include',
          headers: {
            'Access-Control-Allow-Origin': '*'
          }
        }
      }
    }
  }
}
```

### Authentication Helper

```javascript
// plugins/apollo-auth.js
export default (context) => {
  context.app.apolloProvider.defaultClient.wsClient = null

  context.app.apolloProvider.defaultClient.setOnError(({ graphQLErrors, networkError }) => {
    if (graphQLErrors) {
      graphQLErrors.forEach(({ message, locations, path }) => {
        console.log(`[GraphQL error]: Message: ${message}, Location: ${locations}, Path: ${path}`)
      })
    }
    if (networkError) {
      console.log(`[Network error]: ${networkError}`)
    }
  })
}
```

### SEO Implementation

```vue
<!-- components/PostHead.vue -->
<template>
  <div v-if="post">
    <Head>
      <title>{{ post.seo.title }}</title>
      <meta name="description" :content="post.seo.description" />
      <link rel="canonical" :href="post.seo.canonicalUrl" />
      
      <!-- Open Graph -->
      <meta property="og:title" :content="post.seo.openGraph.title" />
      <meta property="og:description" :content="post.seo.openGraph.description" />
      <meta property="og:image" :content="post.seo.openGraph.image.url" />
      <meta property="og:type" :content="post.seo.openGraph.type" />
      
      <!-- Twitter -->
      <meta name="twitter:card" :content="post.seo.twitter.cardType" />
      <meta name="twitter:title" :content="post.seo.twitter.title" />
      <meta name="twitter:description" :content="post.seo.twitter.description" />
      <meta name="twitter:image" :content="post.seo.twitter.image.url" />
    </Head>
  </div>
</template>
```

## Debugging

When `WP_DEBUG` is enabled, the plugin logs detailed information about:

- GraphQL queries and responses
- JWT token generation and validation
- SQL queries for pagination
- SEO data resolution
- CORS requests and headers

Logs are written to your WordPress debug log (typically `wp-content/debug.log`).

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- 📫 For bugs and feature requests, please [create an issue](https://github.com/istogram/wp-graphql-extended/issues)
- 💬 For general questions, please [start a discussion](https://github.com/istogram/wp-graphql-extended/discussions)

## Acknowledgments

- [WPGraphQL](https://github.com/wp-graphql/wp-graphql)
- [The SEO Framework](https://theseoframework.com/)
- [JWT Authentication](https://jwt.io/)
- [Firebase JWT](https://github.com/firebase/php-jwt)