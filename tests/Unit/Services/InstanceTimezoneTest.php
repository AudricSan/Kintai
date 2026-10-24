<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Services\EnvFileWriter;
use kintai\Core\Services\InstanceTimezone;
use PHPUnit\Framework\TestCase;

final class InstanceTimezoneTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kintai-instance-tz-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/.env');
        @rmdir($this->dir);
        unset($_ENV['APP_TIMEZONE'], $_SERVER['APP_TIMEZONE']);
        putenv('APP_TIMEZONE');
    }

    public function testSetWritesTheTimezoneToTheEnvironmentFile(): void
    {
        (new InstanceTimezone(new EnvFileWriter($this->dir)))->set('Asia/Tokyo');

        $this->assertSame("APP_TIMEZONE=Asia/Tokyo\n", file_get_contents($this->dir . '/.env'));
    }

    public function testAnUnknownTimezoneIsRefusedAndNothingIsWritten(): void
    {
        try {
            (new InstanceTimezone(new EnvFileWriter($this->dir)))->set('UTC+9');
            $this->fail('Un fuseau inconnu doit être refusé.');
        } catch (\InvalidArgumentException) {
            $this->assertFileDoesNotExist($this->dir . '/.env');
        }
    }

    public function testCodeIsTheTimezoneInForce(): void
    {
        $this->assertSame(date_default_timezone_get(), (new InstanceTimezone())->code());
        $this->assertContains('Europe/Paris', InstanceTimezone::identifiers());
        $this->assertFalse(InstanceTimezone::isValid('Atlantide'));
    }
}
