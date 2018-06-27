<p align="center"><img width="200" src="src/icon.svg"></p>

# Blitz for Craft CMS

The Blitz plugin provides intelligent static file caching for creating lightning-fast sites with  [Craft CMS](https://craftcms.com/).

## Requirements

Craft CMS 3.0.0 or later.

## Installation

To install the plugin, search for "Blitz" in the Craft Plugin Store, or install manually using composer.

        composer require putyourlightson/craft-blitz

## URI Patterns

URI patterns can use PCRE regular expressions. Below are some common use cases. You can reference the full syntax [here](http://php.net/manual/en/reference.pcre.pattern.syntax.php).

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
