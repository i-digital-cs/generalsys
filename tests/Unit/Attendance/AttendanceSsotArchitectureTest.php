<?php

namespace Tests\Unit\Attendance;

use App\Http\Controllers\Employee\ClockController;
use App\Services\Attendance\AttendanceCalculationEngine;
use App\Services\Attendance\AttendanceHistoryService;
use App\Services\Attendance\ClockPageService;
use App\Services\Attendance\HoursComparisonService;
use App\Services\Portal\PortalStatsService;
use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Architectural guard: presentation layers must not reinvent attendance business math.
 * Prefer dependency boundaries + targeted source checks over brittle whole-repo regex alone.
 */
class AttendanceSsotArchitectureTest extends TestCase
{
    public function test_history_service_depends_on_calculation_engine(): void
    {
        $params = (new ReflectionClass(AttendanceHistoryService::class))
            ->getConstructor()
            ?->getParameters() ?? [];

        $types = array_map(
            fn ($p) => $p->getType() instanceof ReflectionNamedType ? $p->getType()->getName() : null,
            $params,
        );

        $this->assertContains(AttendanceCalculationEngine::class, $types);
    }

    public function test_clock_page_and_controller_depend_on_calculation_engine(): void
    {
        foreach ([ClockPageService::class, ClockController::class] as $class) {
            $params = (new ReflectionClass($class))->getConstructor()?->getParameters() ?? [];
            $types = array_map(
                fn ($p) => $p->getType() instanceof ReflectionNamedType ? $p->getType()->getName() : null,
                $params,
            );
            $this->assertContains(
                AttendanceCalculationEngine::class,
                $types,
                "{$class} must inject AttendanceCalculationEngine",
            );
        }
    }

    public function test_portal_stats_depends_on_calculation_engine_for_late_counts(): void
    {
        $params = (new ReflectionClass(PortalStatsService::class))
            ->getConstructor()
            ?->getParameters() ?? [];
        $types = array_map(
            fn ($p) => $p->getType() instanceof ReflectionNamedType ? $p->getType()->getName() : null,
            $params,
        );
        $this->assertContains(AttendanceCalculationEngine::class, $types);

        $source = file_get_contents((new ReflectionClass(PortalStatsService::class))->getFileName());
        $this->assertNotFalse($source);
        $this->assertStringContainsString('exactLateMinutes', $source);
        $this->assertStringNotContainsString(
            "where('late_minutes', '>', 0)",
            $source,
            'Portal late_today must not SQL-filter stored late_minutes alone',
        );
    }

    public function test_hours_comparison_exposes_exact_early_via_attendance_service(): void
    {
        $source = file_get_contents(
            (new ReflectionClass(HoursComparisonService::class))->getFileName()
        );
        $this->assertNotFalse($source);
        $this->assertStringContainsString('exactEarlyMinutes', $source);
        $this->assertStringContainsString('exactLateMinutes', $source);
        $this->assertStringContainsString('warmScheduledAssignments', $source);
        $this->assertStringNotContainsString(
            '(int) $row->early_departure_minutes',
            $source,
            'HoursComparison must not sum stored early_departure_minutes for month totals',
        );
    }

    public function test_attendance_blade_components_resolve_summary_via_engine(): void
    {
        foreach ([
            resource_path('views/components/attendance-time-metrics.blade.php'),
            resource_path('views/components/attendance-time-breakdown.blade.php'),
        ] as $path) {
            $this->assertFileExists($path);
            $source = file_get_contents($path);
            $this->assertNotFalse($source);
            $this->assertStringContainsString('AttendanceCalculationEngine', $source, $path);
            $this->assertStringNotContainsString(
                'AttendanceTimeCalculator::class)->summarize',
                $source,
                "{$path} must not call AttendanceTimeCalculator::summarize directly",
            );
        }
    }

    public function test_http_controllers_do_not_host_attendance_diff_math(): void
    {
        $finder = (new Finder)
            ->files()
            ->in(app_path('Http/Controllers'))
            ->name('*.php');

        $violations = [];
        foreach ($finder as $file) {
            $source = $file->getContents();
            if (! str_contains($source, 'Attendance') && ! str_contains($file->getRelativePathname(), 'Attendance')
                && ! str_contains($file->getRelativePathname(), 'Clock')) {
                continue;
            }
            if (preg_match('/diffInMinutes\s*\(/', $source)) {
                $violations[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Attendance/Clock controllers must not call diffInMinutes (use AttendanceCalculationEngine)',
        );
    }
}
