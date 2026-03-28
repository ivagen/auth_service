# Auth Service

A Laravel-based authentication microservice using OAuth2 (Laravel Passport). Provides a REST API for user registration, login, logout, and profile retrieval.

## API Endpoints

| Method | Endpoint       | Auth required | Description          |
|--------|----------------|---------------|----------------------|
| POST   | `/api/register` | No           | Register a new user  |
| POST   | `/api/login`    | No           | Login and get token  |
| POST   | `/api/logout`   | Yes          | Revoke access token  |
| GET    | `/api/user`     | Yes          | Get current user     |

Protected endpoints require `Authorization: Bearer <token>` header.

## Requirements

- Docker
- Docker Compose
- Make

## Local Setup

```bash
make bootstrap
```

This single command will:
- Copy `.env.example` to `.env`
- Build and start Docker containers
- Install Composer dependencies
- Generate app key and Passport OAuth keys
- Run database migrations

## Available Commands

| Command             | Description                        |
|---------------------|------------------------------------|
| `make up`           | Start containers                   |
| `make down`         | Stop containers                    |
| `make build`        | Rebuild and start containers       |
| `make logs`         | Follow container logs              |
| `make shell`        | Open shell inside app container    |
| `make migrate`      | Run database migrations            |
| `make fresh`        | Reset database and run seeders     |
| `make test`         | Run tests                          |
| `make cs-fix`       | Fix code style                     |
| `make cs-check`     | Check code style                   |

## Running Tests

```bash
make test
```
