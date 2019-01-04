<p align="center"><img height="120" src="./src/icon.svg"></p>

# Blitz Plugin for Craft CMS 3

The Blitz plugin provides intelligent static file caching for creating lightning-fast sites with [Craft CMS](https://craftcms.com/).

It can highly improve a site’s performance by reducing the time to first byte (TTFB). This reduces the load time of the site as well as the load on the server. Google recommends a server response time of [200ms or less](https://developers.google.com/speed/docs/insights/Server). 

Although the performance gains depend on the individual site and server setup, the following results are not uncommon (on a 5 Mbps cable connection with 28ms of latency):

- 650ms (without caching enabled) 
- 400ms (with caching enabled, without server rewrite) 
- 120ms (with caching enabled and server rewrite)

![TTFB](./docs/images/ttfb-1.2.2.png)  

## Contents

- [License](#license)
- [Requirements](#installation)
- [Usage](#usage)
- [Settings](#settings)
- [How It Works](#how-it-works)
- [Roadmap](#roadmap)

## License

This plugin requires a commercial license which can be purchased through the Craft Plugin Store.  
The license fee is $59 plus $29 per subsequent year for updates (optional).

## Requirements

This plugin requires Craft CMS 3.0.0 or later.

## Usage

Install the plugin from the Craft Plugin Store in your site’s control panel or manually using composer.

```
composer require putyourlightson/craft-blitz
```

After installing the plugin, go to the plugin settings.

1. Turn “Enable Caching” on.
2. Add at least one row to “Included URI Patterns” such as `.*` to cache the entire site.
3. Save the settings and visit the site (it will be cached in the first visit).

Using a [server rewrite](#server-rewrite) (see below) will avoid unnecessary PHP processing and will increase performance even further.

Creating a cron job to [refresh expired cache](#refresh-expired-blitz-cache) (see below) will ensure that URIs that contain elements that have expired since they were cached are automatically refreshed when necessary.

Craft’s template caching `{% cache %}` tag doesn’t play well with the cache breaking feature in Blitz. Template caching also becomes redundant with static file caching, so it is best to either remove all template caching from URLs that Blitz will cache or to simply disable template caching completely in the `config/general.php` file:

```
'enableTemplateCaching' => false,
```

## Settings

### Query String Caching

URLs with query strings will be cached according to the selected option in the "Query String Caching" setting  as follows:

- `Do not cache URLs with query strings`: URLs with query strings (anything following a `?` in a URL) will not be cached. Use when query parameters dynamically affect a page's output and should therefore never be cached.
- `Cache URLs with query strings as unique pages`: URLs with query strings will be cached as unique pages, so `domain.com/`, `domain.com/?=1` and `domain.com/?p=2` will be cached separately. Use when query parameters affect a page’s output in a deterministic way and can therefore be cached as unique pages.
- `Cache URLs with query strings as the same page`: URLs with query strings will be cached as the same page, so `domain.com/`, `domain.com/?&utm_source=twitter` and `domain.com/?&utm_source=facebook` will all be cached with the output. Use when query parameters do not affect a page’s output and can therefore be cached as the same page.

### URI Patterns

URI patterns use PCRE regular expressions. Below are some common use cases. You can reference the full syntax [here](http://php.net/manual/en/reference.pcre.pattern.syntax.php).

- A blank field matches the homepage.
- `.*` matches any character 0 or more times (use this to include everything).
- `.+` matches any character 1 or more times.
- `.` matches any character.
- `\d` matches any digit.
- `\d{4}` matches any four digits.
- `\w` matches any word character.
- `\w+` matches any word character 1 or more times.
- `entries` matches anything containing "entries".
- `^entries` matches anything beginning with "entries".
- `^entries/entry$` matches an exact URI.
- `^entries/\w+$` matches anything beginning with "entries/" followed by at least 1 word character.

### Config Settings

Blitz comes with a config file for a multi-environment way to set the plugin settings. The config file also provides more advanced plugin configuration settings. To use it, copy the `config.php` to your project’s main `config` directory as `blitz.php` and uncomment any settings you wish to change.

![Settings](./docs/images/settings-1.8.0b.png)

## How It Works

When a URL on the site is visited that matches an included URI pattern, Blitz will serve a static cached HTML file if it exists, otherwise it will cache the template output to a HTML file. Excluded URI patterns will override any matching included URI patterns. 

Blitz is compatible with live preview. It will detect when it is being used and will not cache its output or display cached file content (provided the server rewrite, if used, checks for GET requests only).

If a global is saved then Blitz will clear and warm the entire cache if the "Warm Cache Automatically" setting is enabled (and the `warmCacheAutomaticallyForGlobals` config setting has not been set to `false`). This is because globals are available on every page of every site and therefore can potentially affect every cached page. Globals should therefore be used sparingly, only in situations where the global value needs to be accessible from multiple pages. For anything else, consider using entries or categories over globals.

### Dynamic Content

When a URL is cached, the static cached file will be served up on all subsequent requests. Therefore you should ensure that only pages that do not contain any content that needs to dynamically changed per individual request are cached. The easiest way to do this is to add excluded URI patterns for such pages. 

Blitz offers a workaround for injecting dynamic content into a cached page using a Javascript XHR (AJAX) request. The following template tags are available for doing so.

#### `{{ craft.blitz.getUri('/template/name') }}`

Returns a script that injects the contents of the URI provided in place of the twig tag. 

#### `{{ craft.blitz.csrfInput() }}`

Returns a script that injects a CSRF input field in place of the twig tag.

Below is an example of how you might use the tags to create a page containing dynamic content and a form that can be cached by Blitz.

```
Your cart: {{ craft.blitz.getUri('/ajax/cart-items') }}

<form method="post">
   {{ craft.blitz.csrfInput() }}
   ...
 
 </form>
```

In the case above it would make sense to add `ajax/.*` as an excluded URI pattern in the plugin settings.

### Cache Invalidation

When an element is created, updated or deleted, any cached template files that used that element are deleted. If the “Warm Cache Automatically” setting is enabled the a job is  queued to warm the cleared cache files.

The Blitz Cache Utility displays the number of cached URIs for each site. It also provides the following functionality:

#### Clear Blitz Cache
Clearing the cache will delete all cached files.

#### Flush Blitz Cache
Flushing the cache will clear the cache and remove all records from the database.

#### Warm Blitz Cache
Warming the cache will flush the cache and add a job to the queue to recache all of the files.

#### Refresh Expired Blitz Cache
Refreshing the expired cache will refresh all cached URIs that contain elements that have expired since they were cached, specifically entries with future post and expiry dates.

Create a cron job with the following console command to refresh expired cache on a scheduled basis. If entries are generally posted or expire on the hour then a good schedule might be every hour at 5 minutes past the hour. Change `/usr/bin/php` to the PHP path (if different).

```
5 * * * * /usr/bin/php /path/to/craft blitz/cache/refresh-expired
```

#### Manually Clearing Cached Files

Cached files and folders can be cleared manually by simply deleting them on the server.

![Utility](./docs/images/utility-1.11.0.png)

### Console Commands

The following console commands with the functionality described above are also available.

    ./craft blitz/cache/clear
    
    ./craft blitz/cache/flush
    
    ./craft blitz/cache/warm
    
    ./craft blitz/cache/refresh-expired

![Console commands](./docs/images/console-1.8.0a.png)

Note that if the `@web` alias, or any other method that requires a web request, is used to determine the site URL then it cannot be included in cache warming with the console command. Using an absolute site URL is therefore recommended.

### Server Rewrite

For improved performance, adding a server rewrite will avoid the request from ever being processed by Craft once it has been cached. 

#### Apache

In Apache this is achieved with `mod_rewrite` by adding a rewrite rule to the root .htaccess file, just before the rewrites provided by Craft. Change `cache/blitz` to whatever the cache folder path is set to in the plugin settings.

If the "Query String Caching" setting is set to `Do not cache URLs with query strings` or `Cache URLs with query strings as unique pages` then use the following code.

```
# Blitz cache rewrite 
RewriteCond %{DOCUMENT_ROOT}/cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html -s
RewriteCond %{REQUEST_METHOD} GET
RewriteRule .* /cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/%{QUERY_STRING}/index.html [L]

# Send would-be 404 requests to Craft
```

If the "Query String Caching" setting is set to `Cache URLs with query strings as the same page` then use the following code.

```
# Blitz cache rewrite 
RewriteCond %{DOCUMENT_ROOT}/cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/index.html -s
RewriteCond %{REQUEST_METHOD} GET
RewriteRule .* /cache/blitz/%{HTTP_HOST}/%{REQUEST_URI}/index.html [L]

# Send would-be 404 requests to Craft
```

#### Nginx

In Nginx this is achieved by adding a location handler to the configuration file. Change `cache/blitz` to whatever the cache folder path is set to in the plugin settings.

If the "Query String Caching" setting is set to `Do not cache URLs with query strings` or `Cache URLs with query strings as unique pages` then use the following code.

```
# Blitz cache rewrite
location / {
  try_files /cache/blitz/$http_host/$uri/$args/index.html;
}

# Send would-be 404 requests to Craft
```

If the "Query String Caching" setting is set to `Cache URLs with query strings as the same page` then use the following code.

```
# Blitz cache rewrite
location / {
  try_files /cache/blitz/$http_host/$uri/index.html;
}

# Send would-be 404 requests to Craft
```

### Debugging

Cached HTML files are timestamped with a comment at the end of the file. 

```
<!-- Cached by Blitz on 2018-06-27T10:05:00+02:00 -->
```

If the HTML file was served by the plugin rather than with a server rewrite then an additional comment is added.

```
<!-- Served by Blitz -->
```

Note that if your HTML is minified then all comments will be removed from the markup, including the comments above.

If the `sendPoweredByHeader` config setting is not set to `false` then an `X-Powered-By: Blitz` header will be sent.

## Roadmap

- Add better control of local caching of dynamically loaded content.
- Add Blitz to `clear-caches` console command ([#3588](https://github.com/craftcms/cms/pull/3588)).

<small>Created by [PutYourLightsOn](https://putyourlightson.com/).</small>