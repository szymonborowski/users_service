# Users Service

Central user management microservice. Provides dual API access — internal (service-to-service with API key) and external (OAuth 2.0 scopes). Handles user CRUD, role-based access control, and publishes user events to RabbitMQ for downstream services.

## Architecture

```
SSO / Admin / Frontend ──▶ Traefik ──▶ Nginx ──▶ PHP-FPM (Laravel)
                                                      │
                                                 ┌────┴────┐
                                                 ▼         ▼
                                              MySQL    RabbitMQ
                                                    (user events publisher)
```

**Domain:** `users.microservices.local`

## Tech Stack

- **Backend:** PHP 8.5 / Laravel 12
- **Database:** MySQL 8
- **Auth:** Laravel Passport (OAuth 2.0) + Internal API Key middleware
- **Message queue:** RabbitMQ (php-amqplib)
- **API docs:** OpenAPI 3.0 (L5-Swagger)

## API Endpoints

### Internal API (X-Internal-Api-Key header)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/internal/auth/check` | Validate user credentials |
| GET | `/internal/users` | List all users |
| POST | `/internal/users` | Create user |
| GET | `/internal/users/{id}` | Get user by ID |
| PUT | `/internal/users/{id}` | Update user by ID |
| DELETE | `/internal/users/{id}` | Delete user by ID |
| GET | `/internal/roles` | List all roles |
| GET | `/internal/users/{userId}/roles` | Get user roles |
| POST | `/internal/users/{userId}/roles` | Assign role |
| DELETE | `/internal/users/{userId}/roles` | Remove role |

### OAuth 2.0 API (scope: users.read)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/check` | Validate user credentials |
| GET | `/users` | List users |
| GET | `/users/{user}` | Get user |
| POST | `/users` | Create user |
| PUT | `/users/{user}` | Update user |
| DELETE | `/users/{user}` | Delete user |

### Health (Kubernetes probes)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Liveness probe |
| GET | `/ready` | Readiness probe (DB + RabbitMQ) |

## RabbitMQ Events

Publishes events on user changes:

- `user.created` — new user registered
- `user.updated` — user data modified

Consumed by: Blog service (author sync), Analytics service.

## Getting Started

### Prerequisites

- Docker & Docker Compose
- Running infrastructure services (Traefik, RabbitMQ)

### Development

```bash
cp src/.env.example src/.env
# Edit .env with your configuration

docker compose up -d
```

Containers:

| Container | Role | Port |
|-----------|------|------|
| `users-app` | PHP-FPM | 9000 (internal) |
| `users-nginx` | Web server | via Traefik |
| `users-db` | MySQL 8 | 127.0.0.1:3307 |

### Running Tests

```bash
docker compose run --rm --no-deps \
  -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  users-app ./vendor/bin/phpunit
```

### API Documentation

Swagger UI available at `users-swagger.microservices.local` (when Traefik is running).

## Test Coverage

| Metric | Value |
|--------|-------|
| Line coverage | 87.8% |
| Tests | 49 |

## Roadmap

- [x] User CRUD API (internal + OAuth)
- [x] Role-based access control (RBAC)
- [x] RabbitMQ event publishing
- [x] Internal API key authentication
- [x] OpenAPI/Swagger documentation
- [x] Kubernetes manifests and health endpoints
- [ ] Fix 2 failing RabbitMQ update event tests

## License

All rights reserved.
