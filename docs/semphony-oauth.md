# Semphony OAuth connection

BASIS is the OAuth 2.0 authorization server for Semphony. Semphony is a public
Authorization Code client and must use PKCE; it has no client secret.

## First deployment

Run the OAuth migrations and create the signing keys on every environment:

```shell
php artisan migrate --force
php artisan passport:keys
```

Create the production Semphony client once:

```shell
php artisan passport:client --public \
  --name="Semphony" \
  --redirect_uri="https://semphoni.multiscale.nl/integrations/basis/callback"
```

Put the generated client UUID in Semphony as `BASIS_OAUTH_CLIENT_ID`. Configure
the same exact callback as `BASIS_OAUTH_REDIRECT_URI` in Semphony. Redirect URI
matching is exact, including scheme, host, path, and trailing slash.

## Scopes

- `profile:read` reads the connected BASIS identity.
- `samples:read` browses samples the connected user may access.
- `samples:attach` verifies that a sample may be attached to a Semphony session.

Access tokens expire after one hour and refresh tokens after 30 days. Disconnecting
in Semphony revokes the current BASIS access and refresh tokens.

For regular BASIS users, sample access is explicit. A BASIS administrator selects
the authorized users in a sample's **Semphony access** section. Unauthorized
samples are omitted from searches and rejected if requested by their unique ID.
BASIS administrators can verify every sample.

Create a separate public client for local development with its own exact callback
URI. Never reuse production callback URIs for local clients.
