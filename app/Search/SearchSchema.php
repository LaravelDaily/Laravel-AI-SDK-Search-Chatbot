<?php

namespace App\Search;

use App\Enums\CustomerStatus;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use BackedEnum;

final class SearchSchema
{
    public const int MAX_LIMIT = 200;

    /**
     * @var array<string, EntitySchema>|null
     */
    private static ?array $entities = null;

    private function __construct() {}

    /**
     * @return list<string>
     */
    public static function entityNames(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, EntitySchema>
     */
    public static function all(): array
    {
        return self::$entities ??= self::definitions();
    }

    public static function entity(string $name): ?EntitySchema
    {
        return self::all()[$name] ?? null;
    }

    /**
     * Fields that must never be filtered or selected, except tax_id for admins at the runner layer.
     *
     * @return list<string>
     */
    public static function denied(): array
    {
        return [
            'customers.tax_id',
            'request_notes.is_internal',
        ];
    }

    /**
     * @return array<string, EntitySchema>
     */
    private static function definitions(): array
    {
        return [
            'service_requests' => new EntitySchema(
                name: 'service_requests',
                model: ServiceRequest::class,
                fields: self::fields(
                    FieldSchema::make('id', FieldType::Integer, sortable: true, aggregatable: true),
                    FieldSchema::make('customer_id', FieldType::Integer),
                    FieldSchema::make(
                        name: 'customer_name',
                        type: FieldType::String,
                        relationModel: Customer::class,
                        relationColumn: 'name',
                        relationForeignKey: 'customer_id',
                    ),
                    FieldSchema::make('title', FieldType::String, sortable: true),
                    FieldSchema::make('description', FieldType::String),
                    FieldSchema::make(
                        name: 'status',
                        type: FieldType::Enum,
                        sortable: true,
                        enumValues: self::enumValues(ServiceRequestStatus::class),
                    ),
                    FieldSchema::make(
                        name: 'priority',
                        type: FieldType::Enum,
                        sortable: true,
                        enumValues: self::enumValues(ServiceRequestPriority::class),
                    ),
                    FieldSchema::make('opened_at', FieldType::DateTime, sortable: true),
                    FieldSchema::make('closed_at', FieldType::DateTime, sortable: true),
                    FieldSchema::make('assigned_user_id', FieldType::Integer),
                    FieldSchema::make('created_at', FieldType::DateTime, sortable: true),
                ),
                sortKeys: ['id', 'title', 'status', 'priority', 'opened_at', 'closed_at', 'created_at'],
                defaultSort: 'opened_at',
                eagerLoad: ['customer', 'assignedUser'],
                select: [
                    'id',
                    'company_id',
                    'customer_id',
                    'title',
                    'description',
                    'status',
                    'priority',
                    'opened_at',
                    'closed_at',
                    'assigned_user_id',
                    'created_at',
                    'updated_at',
                ],
            ),
            'customers' => new EntitySchema(
                name: 'customers',
                model: Customer::class,
                fields: self::fields(
                    FieldSchema::make('id', FieldType::Integer, sortable: true, aggregatable: true),
                    FieldSchema::make('name', FieldType::String, sortable: true),
                    FieldSchema::make('email', FieldType::String, sortable: true),
                    FieldSchema::make('phone', FieldType::String),
                    FieldSchema::make(
                        name: 'status',
                        type: FieldType::Enum,
                        sortable: true,
                        enumValues: self::enumValues(CustomerStatus::class),
                    ),
                    FieldSchema::make('tax_id', FieldType::String, denied: true),
                    FieldSchema::make('created_at', FieldType::DateTime, sortable: true),
                ),
                sortKeys: ['id', 'name', 'email', 'status', 'created_at'],
                defaultSort: 'created_at',
                select: ['id', 'company_id', 'name', 'email', 'phone', 'status', 'created_at', 'updated_at'],
            ),
            'invoices' => new EntitySchema(
                name: 'invoices',
                model: Invoice::class,
                fields: self::fields(
                    FieldSchema::make('id', FieldType::Integer, sortable: true, aggregatable: true),
                    FieldSchema::make('customer_id', FieldType::Integer),
                    FieldSchema::make(
                        name: 'customer_name',
                        type: FieldType::String,
                        relationModel: Customer::class,
                        relationColumn: 'name',
                        relationForeignKey: 'customer_id',
                    ),
                    FieldSchema::make('service_request_id', FieldType::Integer),
                    FieldSchema::make('total_cents', FieldType::Integer, sortable: true, aggregatable: true),
                    FieldSchema::make('issued_at', FieldType::DateTime, sortable: true),
                    FieldSchema::make('paid_at', FieldType::DateTime, sortable: true),
                    FieldSchema::make('created_at', FieldType::DateTime, sortable: true),
                ),
                sortKeys: ['id', 'total_cents', 'issued_at', 'paid_at', 'created_at'],
                defaultSort: 'issued_at',
                eagerLoad: ['customer', 'serviceRequest'],
                select: [
                    'id',
                    'company_id',
                    'customer_id',
                    'service_request_id',
                    'total_cents',
                    'issued_at',
                    'paid_at',
                    'created_at',
                    'updated_at',
                ],
            ),
        ];
    }

    /**
     * @return array<string, FieldSchema>
     */
    private static function fields(FieldSchema ...$fields): array
    {
        $keyed = [];

        foreach ($fields as $field) {
            $keyed[$field->name] = $field;
        }

        return $keyed;
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return list<string>
     */
    private static function enumValues(string $enum): array
    {
        return array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $enum::cases(),
        );
    }
}
