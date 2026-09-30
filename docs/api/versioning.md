# API Versioning

The API is versioned in the URL path. All routes are served under `/api/v1/...`
(public, admin, `/api/v1/user`, `/api/v1/me`, exports, import schemas).

- A breaking change gets a new major version, e.g. `/api/v2`. The previous version
  can keep running alongside it during a transition period.
- Additive, backwards-compatible changes (new fields, new endpoints) stay in the
  current version and do not bump the path.
- The unversioned `/api/...` paths are a deprecated alias of `/api/v1`: same
  controllers, same middleware, same responses. Every response through the alias
  carries a `Deprecation: true` header and a `Link: <.../api/v1/...>; rel="successor-version"`
  header pointing at the versioned equivalent. `/api/v1` responses never carry these
  headers.
- The session authentication endpoints provided by Laravel Fortify (`/api/login`,
  `/api/logout`, `/api/forgot-password`, `/api/reset-password`) are not part of the
  versioned API and stay under `/api`.

See [Tenant Resolution](tenant-resolution.md) and [Admin API](admin-api.md) for the
endpoints themselves.
