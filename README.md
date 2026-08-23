# Natural Language Search in Laravel

A demo app for a YouTube video that goes past the shiny path of the Laravel AI SDK. The model
produces a *description of a query*. PHP decides what that description is allowed to do.

The headline user story:

> Show me all of customer John Smith's requests in the last year.

That sentence hides tenant isolation, soft deletes, PII, name ambiguity, calendar vs rolling
year, prompt injection in stored notes, and cost control. This repo makes each of those failures
reproducible — and proves the guarded path with an automated eval suite.

Served locally by Laravel Herd at [http://ai-search.test](http://ai-search.test).

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
```

Create a SQLite database if you are not using MySQL/PostgreSQL via Herd:

```bash
touch database/database.sqlite
```

Then seed the deterministic demo dataset (`fake()->seed(1337)`):

```bash
php artisan migrate:fresh --seed
```

Between recording takes, reset to that same state without rebuilding the schema:

```bash
php artisan demo:reset
```

`demo:failures` may insert extra rows (unbounded-result showcase). Always reset before the next take.

## Environment keys

Set at least one provider key. The translator agent uses **Anthropic** (`claude-sonnet-5`) by default,
so `ANTHROPIC_API_KEY` is required for live search and live evals.

| Key | Purpose |
|---|---|
| `ANTHROPIC_API_KEY` | Default translator provider |
| `OPENAI_API_KEY` | Optional second provider |
| `AI_SEARCH_MAX_QUESTION_LENGTH` | Reject questions before they reach the model (default `500`) |
| `AI_SEARCH_RATE_LIMIT_PER_MINUTE` | Per-user search rate limit (default `20`) |
| `AI_SEARCH_STATEMENT_TIMEOUT_MS` | Query timeout where the driver supports it (default `5000`) |
| `AI_SEARCH_INPUT_CENTS_PER_MILLION` | Estimated input cost in cents / 1M tokens (default `300`) |
| `AI_SEARCH_OUTPUT_CENTS_PER_MILLION` | Estimated output cost in cents / 1M tokens (default `1500`) |

No test in the default suite makes a real network call. Live-API tests are behind a Pest group
that is excluded in `phpunit.xml`.

## Demo accounts

Password for every seeded user is `password`.

| Email | Tenant | Role | Use on camera |
|---|---|---|---|
| `acme-admin@example.com` | Acme Services | admin | Generated SQL panel, `/admin/ai-logs`, `tax_id` visible |
| `acme-agent@example.com` | Acme Services | agent | Default search actor |
| `acme-viewer@example.com` | Acme Services | viewer | PII denial (`tax_id` omitted) |
| `globex-agent@example.com` | Globex Ltd | agent | Other-tenant John Smith |

Both tenants own a customer named exactly `John Smith`. Acme also has a second live John Smith and a
soft-deleted Jon Smyth.

Pages:

- Search: [http://ai-search.test/search](http://ai-search.test/search)
- Server-rendered results (no JavaScript): `http://ai-search.test/search/results?q=...`
- Admin audit log: [http://ai-search.test/admin/ai-logs](http://ai-search.test/admin/ai-logs)

## Tests

The default suite is faked. It must stay green without API keys.

```bash
php artisan test
php artisan test --compact
php artisan test --parallel
```

| Group | Command | Network |
|---|---|---|
| Full default suite | `php artisan test --compact` | No |
| Unit (query contract) | `php artisan test --compact --testsuite=Unit` | No |
| Feature | `php artisan test --compact --testsuite=Feature` | No |
| Eval harness (faked) | `php artisan test --compact --testsuite=Evals` | No |
| Failure modes | `php artisan test --compact tests/Feature/FailureModes` | No |
| Guardrails | `php artisan test --compact tests/Feature/Guardrails` | No |
| Live eval vs baseline | `php artisan test --compact --group=live` | **Yes** |

`--group=live` is excluded by default in `phpunit.xml`. It requires `ANTHROPIC_API_KEY` or
`OPENAI_API_KEY` and seeded demo data. Accuracy is compared to `tests/Evals/baseline.json`.

## Evals

Grade **execution accuracy** (result sets), never generated SQL strings.

```bash
php artisan migrate:fresh --seed
php artisan eval:search
php artisan eval:search --model=claude-sonnet-5
php artisan eval:search --runs=3
php artisan eval:search --filter=temporal
```

`--runs=3` runs every golden case three times and prints a consistency score.

Each run writes `storage/app/evals/{timestamp}-{model}.json`.

## Recording

- Architecture and trust boundary: [`docs/architecture.md`](docs/architecture.md)
- One section per failure mode, with the exact on-camera command: [`docs/talking-points.md`](docs/talking-points.md)
- Before/after table for the video's Act 2: `php artisan demo:failures`
- Reset between takes: `php artisan demo:reset`
