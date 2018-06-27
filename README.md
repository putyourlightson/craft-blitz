<p align="center"><img width="200" src="src/icon.svg"></p>

# Blitz Plugin for Craft CMS 3

The Blitz plugin provides intelligent static file caching for creating lightning-fast sites with  [Craft CMS](https://craftcms.com/).

## Requirements

Craft CMS 3.0.0 or later.

## Installation

To install the plugin, search for "Blitz" in the Craft Plugin Store, or install manually using composer.

        composer require putyourlightson/craft-blitz

## Usage

When caching is enabled and a URI on the site is visited that matches an included URI pattern, Blitz will serve a cached HTML file if it exists, otherwise it will cache the template output to a HTML file. Excluded URI patterns will override any matching included URI patterns.

<p><img src="docs/images/settings-1.0.0.png"></p>

## Clearing Cache

When an element is saved or deleted, any cached files for that element and its related elements will be cleared. A job is then automatically queued to refresh the cleared cache files.

Cached files can be cleared in batch using the "Clear Blitz Cache" utility.

<p><img src="docs/images/utility-1.0.0.png"></p>

## URI Patterns

URI patterns use PCRE regular expressions. Below are some common use cases. You can reference the full syntax [here](http://php.net/manual/en/reference.pcre.pattern.syntax.php).

- `.` Matches any character
- `.*` Matches any character 0 or more times
- `.+` Matches any character 1 or more times
- `\d` Matches any digit
- `\d{4}` Matches any four digits
- `\w` Matches any word character
- `\w+` Matches any word character 1 or more times
- `entries/entry` Matches exact URI
- `entries/.*` Matches anything beginning with "entries/"
- `entries/.+` Matches anything beginning with "entries/" followed by at least 1 character

<small>Created by [PutYourLightsOn](https://www.putyourlightson.net/).</small>
