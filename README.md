<p align="center"><img height="120" src="src/icon.svg"></p>
 
# Blitz Plugin for Craft CMS 3

The Blitz plugin provides intelligent static file caching for creating lightning-fast sites with [Craft CMS](https://craftcms.com/).

It can highly improve a site's performance by reducing the time to first byte (TTFB). This reduces the load time of the site as well as the load on the server. Google recommends a server response time of [200ms or less](https://developers.google.com/speed/docs/insights/Server). 

Although the performance gains depend on the individual site and server setup, the following results are not uncommon (on a 5 Mbps cable connection with 28ms of latency):

- 650ms (without caching enabled) 
- 400ms (with caching enabled, without server rewrite) 
- 120ms (with caching enabled and server rewrite)

![TTFB](docs/images/ttfb-1.2.2.png)  

## License

This plugin requires a commercial license which can be purchased through the Craft Plugin Store. The license fee is $49 plus $19 per subsequent year for updates (optional).

## Requirements

Craft CMS 3.0.0 or later.

## Installation

Blitz is available in the Craft Plugin Store and can also be installed manually using composer.

    composer require putyourlightson/craft-blitz

## Usage

In the plugin settings, enable caching and add at least one included URI pattern. When a URI on the site is visited that matches an included URI pattern, Blitz will serve a static cached HTML file if it exists, otherwise it will cache the template output to a HTML file. Excluded URI patterns will override any matching included URI patterns. 

Using a [server rewrite](#server-rewrite) (see below) will avoid unnecessary PHP processing and will increase performance even further.

Blitz is compatible with live preview. It will detect when it is being used and will not cache its output or display cached file content (provided the server rewrite, if used, checks for GET requests only).

![Settings](docs/images/settings-1.8.0b.png)

## URI Patterns

URI patterns use PCRE regular expressions. Below are some common use cases. You can reference the full syntax [here](http://php.net/manual/en/reference.pcre.pattern.syntax.php).

- `.*` Matches any character 0 or more times (use this to include everything)
- `.+` Matches any character 1 or more times
- `.` Matches any character
- `\d` Matches any digit
- `\d{4}` Matches any four digits
- `\w` Matches any word character
- `\w+` Matches any word character 1 or more times
- `entries` Matches anything containing "entries"
- `^entries` Matches anything beginning with "entries"
- `^entries/entry$` Matches an exact URI
- `^entries/\w+$` Matches anything beginning with "entries/" followed by at least 1 word character

## Server Rewrite

For improved performance, adding a server rewrite will avoid the request from ever being processed by Craft once it has been cached. 

### Apache

In Apache this is achieved with `mod_rewrite` by adding a rewrite rule to the root .htaccess file, just before the rewrites provided by Craft. Change `cache/blitz` to whatever the cache folder path is set to in the plugin settings.

If the "Query String Caching" setting is set to `Do not cache URLs with query strings` or `Cache URLs with query strings as unique pages` then use the following code.

    # Blitz cache rewrite 
    RewriteCond %{DOCUMENT_ROOT}/cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html -s
    RewriteCond %{REQUEST_METHOD} GET
    RewriteRule .* /cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html [L]
    
    # Send would-be 404 requests to Craft

If the "Query String Caching" setting is set to `Cache URLs with query strings as the same page` then use the following code.

    # Blitz cache rewrite 
    RewriteCond %{DOCUMENT_ROOT}/cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/index.html -s
    RewriteCond %{REQUEST_METHOD} GET
    RewriteRule .* /cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/index.html [L]
    
    # Send would-be 404 requests to Craft

### Nginx

In Nginx this is achieved by adding a location handler to the configuration file. Change `cache/blitz` to whatever the cache folder path is set to in the plugin settings.

If the "Query String Caching" setting is set to `Do not cache URLs with query strings` or `Cache URLs with query strings as unique pages` then use the following code.

    # Blitz cache rewrite
    location / {
      try_files /cache/blitz/$http_host/$uri/$args/index.html;
    }
    
    # Send would-be 404 requests to Craft

If the "Query String Caching" setting is set to `Cache URLs with query strings as the same page` then use the following code.

    # Blitz cache rewrite
    location / {
      try_files /cache/blitz/$http_host/$uri/index.html;
    }
    
    # Send would-be 404 requests to Craft

## Cache Invalidation

When an element is created, updated or deleted, any cached template files that used that element are deleted. A job is then automatically queued to refresh the cleared cache files. This applies to all element types, including global sets.

The "Blitz Cache" utility allows users to clear and warm the cache. Warming the cache will first clear the cache and then add a job to the queue to pre-cache files. Cached files and folders can be cleared manually using the utility or by simply deleting them on the server.

![Utility](docs/images/utility-1.8.0.png)

## Console Commands

Console commands can also be used to warm or clear all cache as follows:

    ./craft blitz/cache/warm
    
    ./craft blitz/cache/clear
    
![Console commands](docs/images/console-1.8.0a.png)

Note that if the `@web` alias is used in a site URL then it is only available to web requests and will therefore not be included in cache warming with the console command. 

## Considerations

Craft's template caching (`{% cache %}`) tag does not play well with the cache breaking feature in Blitz. Template caching also becomes redundant with static file caching, so it is best to remove all template caching from URIs that Blitz will cache.

URIs with query strings will be cached according to the selected option in the "Query String Caching" setting  as follows:

- `Do not cache URLs with query strings`: URIs with query strings (anything following a `?` in a URI) will not be cached.
- `Cache URLs with query strings as unique pages`: URIs with query strings will be cached as unique pages, so `domain.com`, `domain.com?p=1` and `domain.com?p=2` will be cached separately.
- `Cache URLs with query strings as the same page`: URIs with query strings will be cached as the same page, so `domain.com`, `domain.com?p=1` and `domain.com?p=2` will all be cached with the output.

When a URI is cached, the static cached file will be served up on all subsequent requests. Therefore you should ensure that only pages that do not contain any content that needs to dynamically changed per individual request are cached. The easiest way to do this is to add excluded URI patterns for such pages. 

Pages that display the following should in general _not_ be cached:
- Logged-in user specific content such as username, orders, etc.
- Forms that use CSRF protection
- Shopping carts and checkout pages

## Debugging

Cached HTML files are timestamped with a comment at the end of the file. 

    <!-- Cached by Blitz on 2018-06-27T10:05:00+02:00 -->

If the HTML file was served by the plugin rather than with a server rewrite then an additional comment is added.

    <!-- Served by Blitz -->
  
---

<small>Created by [PutYourLightsOn](https://putyourlightson.com/).</small>
