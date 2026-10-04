<?php

use App\Search\SqlFormatter;

it('puts each clause and condition on its own line and wraps long column lists', function () {
    $sql = 'select `service_requests`.`id`, `service_requests`.`company_id`, `service_requests`.`customer_id`, `service_requests`.`title` '
        .'from `service_requests` where `service_requests`.`company_id` = 1 and `service_requests`.`status` = \'open\' '
        .'and `service_requests`.`deleted_at` is null order by `service_requests`.`opened_at` desc limit 50';

    expect(SqlFormatter::format($sql))->toBe(implode("\n", [
        'select `service_requests`.`id`, `service_requests`.`company_id`,',
        '  `service_requests`.`customer_id`, `service_requests`.`title`',
        'from `service_requests`',
        'where `service_requests`.`company_id` = 1',
        '  and `service_requests`.`status` = \'open\'',
        '  and `service_requests`.`deleted_at` is null',
        'order by `service_requests`.`opened_at` desc',
        'limit 50',
    ]));
});

it('leaves keywords and commas inside string literals and between ranges alone', function () {
    $sql = 'select * from `service_requests` where `title` like \'%rock and roll, from where%\' '
        .'and `opened_at` between \'2025-01-01\' and \'2025-12-31\'';

    expect(SqlFormatter::format($sql))->toBe(implode("\n", [
        'select *',
        'from `service_requests`',
        'where `title` like \'%rock and roll, from where%\'',
        '  and `opened_at` between \'2025-01-01\' and \'2025-12-31\'',
    ]));
});
