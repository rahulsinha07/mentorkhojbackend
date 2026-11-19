<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\GoogleAdsLeadWebhookController;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class GoogleAdsLeadWebhookRetryTest extends TestCase
{
    private function callIsTransient(string $message): bool
    {
        $controller = (new ReflectionClass(GoogleAdsLeadWebhookController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(GoogleAdsLeadWebhookController::class, 'isTransientConnectionError');
        $method->setAccessible(true);

        $e = new QueryException('mysql', 'select 1', [], new \PDOException($message));

        return $method->invoke($controller, $e);
    }

    public function test_detects_operation_not_permitted_as_transient(): void
    {
        $this->assertTrue($this->callIsTransient(
            "SQLSTATE[HY000] [2002] Operation not permitted (Connection: mysql, SQL: select 1)"
        ));
    }

    public function test_detects_standard_laravel_lost_connection_message(): void
    {
        $this->assertTrue($this->callIsTransient('MySQL server has gone away'));
    }

    public function test_does_not_flag_duplicate_key_error_as_transient(): void
    {
        $this->assertFalse($this->callIsTransient(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'demo_bookings_external_lead_id_unique'"
        ));
    }
}
