<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Natural-language search guardrails
    |--------------------------------------------------------------------------
    |
    | Hard limits applied in PHP before a question reaches the model, and
    | around query execution afterwards. These are independent of whatever
    | the translator emits.
    |
    */

    'max_question_length' => (int) env('AI_SEARCH_MAX_QUESTION_LENGTH', 500),

    'rate_limit_per_minute' => (int) env('AI_SEARCH_RATE_LIMIT_PER_MINUTE', 20),

    'statement_timeout_ms' => (int) env('AI_SEARCH_STATEMENT_TIMEOUT_MS', 5000),

    /*
    |--------------------------------------------------------------------------
    | Executed SQL visibility
    |--------------------------------------------------------------------------
    |
    | Admins always see the SQL that ran under the search results. Enable this
    | to show it to every role, e.g. while recording a demo.
    |
    */

    'show_sql_to_all_roles' => (bool) env('AI_SEARCH_SHOW_SQL_TO_ALL_ROLES', false),

    /*
    |--------------------------------------------------------------------------
    | Estimated token cost
    |--------------------------------------------------------------------------
    |
    | Prices are stored as integer cents per million tokens so estimated_cost_cents
    | can be computed without floating point currency. Defaults match a mid-tier
    | Claude Sonnet-class rate ($3 / $15 per million).
    |
    */

    'cost' => [
        'input_cents_per_million_tokens' => (int) env('AI_SEARCH_INPUT_CENTS_PER_MILLION', 300),
        'output_cents_per_million_tokens' => (int) env('AI_SEARCH_OUTPUT_CENTS_PER_MILLION', 1500),
    ],

];
