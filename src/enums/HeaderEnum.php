<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\enums;

enum HeaderEnum: string
{
    case ACCEPT_ENCODING = 'Accept-Encoding';
    case CACHE_CONTROL = 'Cache-Control';
    case CACHE_TAG = 'Cache-Tag';
    case CONTENT_ENCODING = 'Content-Encoding';
    case CONTENT_TYPE = 'Content-Type';
    case PERMISSIONS_POLICY = 'Permissions-Policy';
    case X_POWERED_BY = 'X-Powered-By';
    case X_ROBOTS_TAG = 'X-Robots-Tag';
}
