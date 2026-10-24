<?php

namespace Tests\Unit;

use App\Services\WorkforceCacheRegistry;
use Illuminate\Contracts\Cache\Repository;
use PHPUnit\Framework\TestCase;

final class WorkforceCacheRegistryTest extends TestCase
{
    public function test_object_cache_invalidates_legacy_incomplete_class_and_rebuilds_read_model(): void
    {
        $cache = $this->createMock(Repository::class);
        $cache->expects(self::once())->method('get')->with('schema:workforce:8:attendance:version', 0)->willReturn(3);
        $cache->expects(self::once())->method('remember')->with('schema:workforce:8:attendance:v3:detail:41', 120, self::isInstanceOf(\Closure::class))->willReturn(unserialize('O:13:"MissingClassX":0:{}'));
        $cache->expects(self::once())->method('forget')->with('schema:workforce:8:attendance:v3:detail:41')->willReturn(true);

        $actual = (new WorkforceCacheRegistry($cache))->remember(8, 'attendance', 'detail:41', 120, static fn (): array => ['rebuilt' => true]);

        self::assertSame(['rebuilt' => true], $actual);
    }
}
