<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * '*' (trust all): Cloud Run terminates TLS at Google's edge and forwards plain HTTP to the container -
     * the container is only ever reachable through that edge, never directly from the internet, so there's
     * no untrusted party that could spoof these headers. Left unset (the framework default, effectively
     * "trust none"), Laravel ignores X-Forwarded-Proto entirely and believes every request is plain HTTP,
     * which made `route()`/`url()` generate http:// links even on an HTTPS page - confirmed against a real
     * deploy: the admin login form's action="{{ route('admin.authenticate') }}" resolved to http://, Chrome
     * warned about submitting an insecure form, and once "confirmed", Cloud Run's http->https redirect
     * silently downgraded the POST to a GET, turning login into a 405.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
