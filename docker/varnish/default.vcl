# Varnish 7 configuration for Drupal 11.
# Based on the community Drupal VCL; tuned for a single nginx backend.
vcl 4.1;

import std;

backend default {
    .host = "nginx";
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 300s;
    .between_bytes_timeout = 60s;
}

# Hosts allowed to send PURGE / BAN requests (docker networks are private).
acl purge {
    "localhost";
    "127.0.0.1";
    "10.0.0.0"/8;
    "172.16.0.0"/12;
    "192.168.0.0"/16;
}

sub vcl_recv {
    # Cache invalidation from Drupal (purge / varnish_purge modules).
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        return (purge);
    }
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        if (req.http.Cache-Tags) {
            ban("obj.http.Cache-Tags ~ " + req.http.Cache-Tags);
            return (synth(200, "Ban added."));
        }
        if (req.http.X-Url) {
            ban("obj.http.X-Url == " + req.http.X-Url);
            return (synth(200, "Ban added."));
        }
        return (synth(403, "Cache-Tags or X-Url header missing."));
    }
    if (req.method == "URIBAN") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        ban("req.http.host == " + req.http.host + " && req.url == " + req.url);
        return (synth(200, "Ban added."));
    }

    # Only cache GET/HEAD.
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Never cache admin, user or authenticated-only paths.
    if (req.url ~ "^/(admin|user|batch|update\.php|core/install\.php|cron)" ||
        req.url ~ "^/system/files/" ||
        req.url ~ "^/(en|fi|sv)/(admin|user)") {
        return (pass);
    }

    # Pass requests that carry a Drupal session or the "no cache" cookie.
    if (req.http.Cookie ~ "(^|;\s*)(S?SESS[a-zA-Z0-9]*|NO_CACHE)=") {
        return (pass);
    }

    # Strip all other cookies so anonymous pages are cacheable.
    unset req.http.Cookie;

    # Normalize Accept-Encoding for a better hit rate.
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|svgz|webp|woff2?)$") {
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } else {
            unset req.http.Accept-Encoding;
        }
    }

    return (hash);
}

sub vcl_hash {
    hash_data(req.url);
    if (req.http.host) {
        hash_data(req.http.host);
    } else {
        hash_data(server.ip);
    }
    return (lookup);
}

sub vcl_backend_response {
    # Keep URL on the object so it can be banned by URL.
    set beresp.http.X-Url = bereq.url;
    set beresp.http.X-Host = bereq.http.host;

    # Don't cache when Drupal says not to.
    if (beresp.http.Cache-Control ~ "(private|no-cache|no-store)" ||
        beresp.http.Set-Cookie ||
        beresp.http.Vary == "*") {
        set beresp.uncacheable = true;
        set beresp.ttl = 120s;
        return (deliver);
    }

    # Serve stale content while the backend refreshes / is down.
    set beresp.grace = 6h;

    # Cache static files for a day regardless of backend headers.
    if (bereq.url ~ "\.(css|js|png|gif|jpe?g|ico|svg|webp|woff2?|ttf|eot)(\?.*)?$") {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 1d;
    }

    return (deliver);
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT";
    } else {
        set resp.http.X-Varnish-Cache = "MISS";
    }

    # Tidy internal headers before sending to the client.
    unset resp.http.X-Url;
    unset resp.http.X-Host;
    unset resp.http.X-Drupal-Cache-Tags;
    unset resp.http.X-Drupal-Cache-Contexts;
    unset resp.http.X-Drupal-Cache-Max-Age;
    unset resp.http.X-Generator;
    unset resp.http.X-Powered-By;
    unset resp.http.Via;
    unset resp.http.X-Varnish;
}
