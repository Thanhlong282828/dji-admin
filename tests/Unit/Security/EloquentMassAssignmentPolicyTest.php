<?php

namespace Tests\Unit\Security;

use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendance\Domain\Models\AttendanceEvent;
use Modules\Company\Domain\Models\Company;
use Modules\Employee\Domain\Models\Employee;
use Modules\Payroll\Domain\Models\PayrollAnnualTaxReconciliation;
use Modules\Workflow\Domain\Models\WorkflowDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

final class EloquentMassAssignmentPolicyTest extends TestCase
{
    /** @return iterable<string, array{class-string<Model>}> */
    public static function convertedModelProvider(): iterable
    {
        foreach (self::modelClasses() as $class) {
            $reflection = new ReflectionClass($class);
            if ($reflection->getFileName() !== false && str_contains($reflection->getFileName(), '/Modules/')) {
                yield $class => [$class];
            }
        }
    }

    /** @param class-string<Model> $class */
    #[DataProvider('convertedModelProvider')]
    public function test_converted_models_use_an_explicit_allowlist_and_protect_system_columns(string $class): void
    {
        $model = new $class;

        $guarded = (new ReflectionClass($class))->getProperty('guarded');
        self::assertNotSame($class, $guarded->getDeclaringClass()->getName(), "{$class} must not retain a guarded declaration.");
        foreach (['id', 'uuid', 'created_at', 'updated_at', 'deleted_at'] as $attribute) {
            self::assertFalse($model->isFillable($attribute), "{$class} unexpectedly allows {$attribute}.");
        }

        foreach (['id', 'uuid', 'created_at', 'updated_at', 'deleted_at'] as $attribute) {
            $this->assertMassAssignmentRejected(new $class, $attribute, 'attacker-controlled');
        }
    }

    public function test_expected_internal_contract_fields_remain_mass_assignable(): void
    {
        $event = new AttendanceEvent;
        $event->fill(['company_id' => 17, 'source' => 'terminal']);

        self::assertSame(17, $event->getAttribute('company_id'));
        self::assertSame('terminal', $event->getAttribute('source'));
    }

    public function test_query_only_models_reject_arbitrary_mass_assignment(): void
    {
        foreach ([new WorkflowDefinition, new PayrollAnnualTaxReconciliation] as $model) {
            self::assertSame([], $model->getFillable());
            $this->assertMassAssignmentRejected($model, 'company_id', 12);
            $this->assertMassAssignmentRejected($model, 'status', 'approved');
        }
    }

    public function test_high_risk_legacy_protected_columns_remain_blocked(): void
    {
        self::assertFalse((new Company)->isFillable('uuid'));
        self::assertFalse((new Company)->isFillable('deleted_at'));
        self::assertFalse((new Employee)->isFillable('uuid'));
        self::assertFalse((new Employee)->isFillable('deleted_at'));
    }

    /** @return list<class-string<Model>> */
    private static function modelClasses(): array
    {
        $root = dirname(__DIR__, 3);
        $files = array_merge(
            glob($root.'/app/Models/*.php') ?: [],
            glob($root.'/Modules/*/app/Domain/Models/*.php') ?: [],
            glob($root.'/Modules/*/app/Models/*.php') ?: [],
            glob($root.'/Modules/*/app/Infrastructure/Models/*.php') ?: [],
        );
        $classes = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            if (! is_string($source)
                || preg_match('/namespace\s+([^;]+);/', $source, $namespace) !== 1
                || preg_match('/(?:final\s+|abstract\s+)?class\s+(\w+)/', $source, $name) !== 1) {
                continue;
            }
            $class = $namespace[1].'\\'.$name[1];
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }
            /** @var class-string<Model> $class */
            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    private function assertMassAssignmentRejected(Model $model, string $attribute, mixed $value): void
    {
        try {
            $model->fill([$attribute => $value]);
            self::assertArrayNotHasKey($attribute, $model->getAttributes());
        } catch (MassAssignmentException) {
            self::assertArrayNotHasKey($attribute, $model->getAttributes());
        }
    }
}
