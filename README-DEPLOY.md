# Render deployment and HTTP API

This repository is a PHP SMTP email-validation library with a small authenticated HTTP API wrapper for deployment as a Render Docker web service.

## Render configuration

- **Runtime:** Docker
- **Dockerfile path:** `./Dockerfile`
- **Branch:** `master`
- **Health-check path:** `/health`
- Add these environment variables in the Render dashboard:
  - `API_KEY`: a long, random secret used as a bearer token.
  - `SENDER_EMAIL`: a valid sender email address you control.

Generate a key locally with `openssl rand -hex 32`. Do not commit secrets to GitHub.

## Endpoints

### Health check

```sh
curl https://YOUR-RENDER-SERVICE.onrender.com/health
```

Expected response:

```json
{"status":"ok"}
```

### Validate one email address

```sh
curl -X POST 'https://YOUR-RENDER-SERVICE.onrender.com/validate' \
  -H 'Authorization: Bearer YOUR_API_KEY' \
  -H 'Content-Type: application/json' \
  --data '{"email":"recipient@example.com"}'
```

The response includes the library's raw validation result. SMTP-based recipient verification is heuristic: providers can reject probes, throttle connections, or hide whether a mailbox exists. A result is not a guarantee of message delivery.

## Security and operational notes

- Keep `API_KEY` private and rotate it if exposed.
- Use an email address and domain you are authorized to send from.
- Avoid high-volume or abusive probing; remote mail servers may rate-limit or block checks.
