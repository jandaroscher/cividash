# HTTP security headers

Most HTTP security headers depend on how a deployment is operated: whether the whole domain
is served over TLS, which sites may embed tiles, and which object storage serves media. CiviDash
therefore sets only a safe subset itself and leaves the rest to the operator, who adds them at
the TLS-terminating reverse proxy, ingress or gateway. This page lists what the application
sends and what we recommend adding.

## What CiviDash sends

| Header | Docker images (`cividash-web`) | Standalone install |
|---|---|---|
| `X-Content-Type-Options: nosniff` | yes | no |
| `X-Frame-Options: SAMEORIGIN` | yes, except on tile embed routes | no |
| `Referrer-Policy: strict-origin-when-cross-origin` | yes | no |
| `Strict-Transport-Security` | no | no |
| `Content-Security-Policy` | no | no |
| `Permissions-Policy` | no | no |

The web server of the Docker images is configured in
[`docker/production/nginx/nginx.conf`](../../docker/production/nginx/nginx.conf). A standalone
install sends none of these headers unless your web server adds them, see
[installation-standalone.md](installation-standalone.md).

Tile embeds (`/embed/tiles/{slug}` and `/en/embed/tiles/{slug}`) are meant to be framed by
other websites, through `public/embed.js` or a plain iframe. Any header that restricts framing
must exempt these two paths, or embedded tiles stay blank.

## Recommended headers for operators

| Header | Recommended value | Notes |
|---|---|---|
| `Strict-Transport-Security` | `max-age=31536000` | Only once every URL of the host is served over HTTPS. Start with a short `max-age` (e.g. `300`) and raise it after checking. Add `includeSubDomains` only if every subdomain is HTTPS too, which matters when tenants use subdomains. |
| `Content-Security-Policy` | see below | Roll out as `Content-Security-Policy-Report-Only` first. |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` | CiviDash uses none of these browser features. |
| `X-Content-Type-Options` | `nosniff` | Standalone installs only; the Docker images already send it. |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Standalone installs only; the Docker images already send it. |

Do not add `X-Frame-Options` at the proxy in front of the Docker images. The image already
sends it, and a proxy that adds it to every response also adds it to the tile embed routes,
which the image deliberately leaves without it. To restrict which sites may embed tiles, use
the CSP `frame-ancestors` directive instead.

### Content-Security-Policy

Starting policy for all paths except the tile embed routes:

```text
default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
```

For `/embed/tiles/` and `/en/embed/tiles/`, use the same policy with `frame-ancestors *`, or
with the list of origins that may embed tiles (e.g. `frame-ancestors 'self' https://www.example.org`).

Why the relaxations are needed:

- `'unsafe-inline'` in `script-src`: the SPA boots with an inline script that passes the tenant
  to the frontend, and the admin panel (Filament, Livewire) uses inline scripts.
- `'unsafe-eval'` in `script-src`: Livewire and Alpine.js in the admin panel evaluate
  expressions at runtime. It also covers the WebAssembly module of the Lottie player used for
  animated tiles.
- `'unsafe-inline'` in `style-src`: the frontend and the admin panel set inline styles.
- `https://fonts.bunny.net` in `style-src` and `font-src`: the admin panel loads its font from
  there. The public frontend serves all fonts locally.
- `data:` in `img-src`: the admin panel uses inline SVG icons as data URLs.

Extend the policy for your deployment:

- Media on S3 (`PUBLIC_DISK_DRIVER=s3`): add the public bucket origin (the host of
  `FILESYSTEM_PUBLIC_URL` or `AWS_URL`) to `img-src`, and to `media-src` if editors upload
  video or audio.
- Keycloak single sign-on: the login is a plain redirect and needs no CSP change. If your
  browser console reports a `form-action` violation on login, add the Keycloak origin to
  `form-action`.

This policy was checked against a running instance: public dashboard (German and English),
tile embed page, a third-party page embedding a tile through `embed.js` and an iframe, the admin
login and the tile and page lists and editors. It produced no CSP violations. Removing any of
the relaxations above produced violations or broken pages. Paths that were not covered (Lottie
tiles, media uploads, single sign-on, S3 media) may need additions, which is why the policy
should start in report-only mode.

## Examples

nginx, in the `server` block of the TLS-terminating proxy in front of CiviDash:

```nginx
add_header Strict-Transport-Security "max-age=31536000" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()" always;

set $cividash_csp "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'";
set $cividash_frame_ancestors "'self'";
if ($request_uri ~* "^/(en/)?embed/tiles/") {
    set $cividash_frame_ancestors "*";
}
add_header Content-Security-Policy-Report-Only "$cividash_csp; frame-ancestors $cividash_frame_ancestors" always;
```

nginx drops all server-level `add_header` lines in a `location` that has its own `add_header`.
Repeat the headers there, as `docker/production/nginx/nginx.conf` does.

Apache (needs `mod_headers`), in the virtual host:

```apache
Header always set Strict-Transport-Security "max-age=31536000"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=()"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

Define CIVIDASH_CSP "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'"
<If "%{REQUEST_URI} =~ m#^/(en/)?embed/tiles/#">
    Header always set Content-Security-Policy-Report-Only "${CIVIDASH_CSP}; frame-ancestors *"
</If>
<Else>
    Header always set Content-Security-Policy-Report-Only "${CIVIDASH_CSP}; frame-ancestors 'self'"
</Else>
```

On Kubernetes, set the same values in your ingress controller or gateway, e.g. a Traefik
`headers` middleware or the APISIX `response-rewrite` plugin. For the CIVITAS/CORE add-on, see
its Helm chart documentation.

Once the browser console stays free of CSP reports in normal use, switch
`Content-Security-Policy-Report-Only` to `Content-Security-Policy`.
