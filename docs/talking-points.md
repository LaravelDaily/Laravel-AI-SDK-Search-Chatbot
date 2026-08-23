# Talking points

Reset to a known state before every take:

```bash
php artisan demo:reset
```

The full before/after table (Act 2 opener):

```bash
php artisan demo:failures
```

Each section below is one failure mode. Run the command on camera, then say the line in
**On camera**. The Pest filter string is the test name from
`tests/Feature/FailureModes/FailureModesTest.php`.

Sign-in for UI takes: `acme-agent@example.com` / `password` at
[http://ai-search.test/login](http://ai-search.test/login). Admin takes use
`acme-admin@example.com`. Viewer / PII takes use `acme-viewer@example.com`.

---

## 1. Soft deletes

A query that forgets `deleted_at` returns a visibly inflated count. The seeder soft-deletes
~15% of requests, concentrated in the last 12 months.

```bash
php artisan test --compact --filter="soft deletes"
```

**On camera:** "The naive count is higher than the real count. The guarded runner always
applies `whereNull('deleted_at')`. There is no flag to include trash."

UI follow-up (after reset):

> show me last year's requests

---

## 2. Tenant leak

Both tenants own a customer named exactly `John Smith`. A query that filters
`company_id = Globex` while acting as an Acme user is the headline security test.

```bash
php artisan test --compact --filter="tenant leak"
```

**On camera:** "The naive runner returns Globex's John Smith to an Acme user. The real runner
returns zero rows — tenant scope is applied in PHP after validation, unconditionally, no
matter what the model emitted."

UI follow-up: stay logged in as Acme. The other tenant's John Smith must never appear.

```bash
php artisan demo:reset
```

---

## 3. PII exposure

Every customer has a `tax_id`. Viewers must never see it. The model must never see it.

```bash
php artisan test --compact --filter="PII exposure"
```

**On camera:** "Naive result rows contain `tax_id`. The guarded runner strips denied columns
unless the actor is an admin. The translator prompt is built from `SearchSchema` — `tax_id`
is on the deny list, so it never reaches the model."

UI follow-up: log in as `acme-viewer@example.com`, search customers, confirm no tax IDs.
Then `acme-admin@example.com` to show the column is role-gated, not missing.

```bash
php artisan demo:reset
```

---

## 4. Ambiguous entity

Acme has two live `John Smith`s and a soft-deleted `Jon Smyth`. The agent must not resolve
names to IDs.

```bash
php artisan test --compact --filter="ambiguous entity"
```

**On camera:** "Naive silently picks the first John Smith. The real resolver returns the two
live candidates and omits the soft-deleted Jon Smyth. We do not guess."

UI follow-up (the reference user story):

> show me all of customer John Smith's requests in the last year

The page should render a disambiguation list, not results.

```bash
php artisan demo:reset
```

---

## 5. Rolling vs calendar year

"Last year" is not one window. The seeder places rows on both sides of the calendar-year
boundary and the rolling 12-month boundary.

```bash
php artisan test --compact --filter="rolling vs calendar year"
```

**On camera:** "The two interpretations return different counts against the same seeded
data. The interpretation sentence has to say which window was used — otherwise the number
is a lie."

UI follow-up: run both phrasings and read the interpretation chip aloud.

> show me last year's requests
>
> show me requests in calendar year 2026

```bash
php artisan demo:reset
```

---

## 6. Hallucinated field

A faked model response uses `customers.is_vip`, which does not exist.

```bash
php artisan test --compact --filter="hallucinated field"
```

**On camera:** "Naive throws a raw SQL error. The real path validates against `SearchSchema`,
returns a clean reason, and gives the model exactly one repair attempt. A second failure is
a `TranslationFailed` result — not an exception in the user's face."

---

## 7. Unbounded result set

A query with `limit: 500` (or no effective cap) will dump the table.

```bash
php artisan test --compact --filter="unbounded result set"
```

**On camera:** "Naive returns everything. The runner clamps to 200 and sets `truncated`.
Limits live in PHP. The model does not get a vote."

This test inserts extra rows. Reset before the next take:

```bash
php artisan demo:reset
```

---

## Eval harness (the payoff)

"How do you know it works?" — with a number, graded on result sets, never SQL text.

Faked harness (no network, safe anytime):

```bash
php artisan test --compact --testsuite=Evals
```

Live run (needs `ANTHROPIC_API_KEY`):

```bash
php artisan demo:reset
php artisan eval:search
php artisan eval:search --runs=3
```

**On camera:** "`--runs=3` is how we show non-determinism honestly. Same question, three
times, a consistency score. The committed baseline is `tests/Evals/baseline.json` — a
regression fails when someone runs `--group=live`."

```bash
php artisan test --compact --group=live
```

---

## Shot list (suggested order)

1. `php artisan demo:reset` — "known state."
2. Login as `acme-agent@example.com`, paste the John Smith question, show disambiguation.
3. `php artisan demo:failures` — seven-row before/after table.
4. Walk the table top to bottom; for each row, cut to the matching `php artisan test --filter=...`.
5. Login as viewer, show missing `tax_id`; login as admin, show `/admin/ai-logs` and SQL.
6. Edit a filter chip; say "no second AI call."
7. `php artisan eval:search --runs=3` — accuracy, consistency, cost / 1k searches.
