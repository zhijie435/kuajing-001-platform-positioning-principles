<?php

declare(strict_types=1);

final class StateReset
{
    private static $defaults = [];

    public static function snapshot(): void
    {
        self::$defaults = [];
        foreach (self::targetClasses() as $class => $props) {
            foreach ($props as $prop) {
                self::$defaults[$class][$prop] = self::readStatic($class, $prop);
            }
        }
    }

    public static function resetAll(): void
    {
        foreach (self::targetClasses() as $class => $props) {
            foreach ($props as $prop) {
                $value = self::$defaults[$class][$prop] ?? null;
                self::writeStatic($class, $prop, $value);
            }
        }
        self::destroySession();
    }

    public static function resetClass(string $class): void
    {
        $props = self::targetClasses()[$class] ?? [];
        foreach ($props as $prop) {
            $value = self::$defaults[$class][$prop] ?? null;
            self::writeStatic($class, $prop, $value);
        }
    }

    private static function targetClasses(): array
    {
        return [
            PlatformGuard::class => [
                'currentPlatform',
                'auditLog',
                'violations',
            ],
            CommercialGuard::class => [
                'violations',
                'activeLicense',
            ],
            RedLineGuard::class => [
                'requestCache',
                'redLineEvents',
                'platformConfigCache',
                'allPlatformConfigCache',
                'configStorageFile',
                'sessionStarted',
            ],
            LicenseStore::class => [
                'cache',
            ],
        ];
    }

    private static function readStatic(string $class, string $prop)
    {
        $ref = new ReflectionProperty($class, $prop);
        $ref->setAccessible(true);
        return $ref->getValue();
    }

    private static function writeStatic(string $class, string $prop, $value): void
    {
        $ref = new ReflectionProperty($class, $prop);
        $ref->setAccessible(true);
        $ref->setValue(null, $value);
    }

    private static function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
        $_SESSION = [];
    }
}
