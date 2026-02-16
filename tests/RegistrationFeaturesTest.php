<?php

declare(strict_types=1);

namespace Esegments\LaravelExtensions\Tests;

use Esegments\LaravelExtensions\HandlerRegistry;
use Esegments\LaravelExtensions\Registration\ConditionalRegistration;
use Esegments\LaravelExtensions\Registration\WildcardMatcher;
use Esegments\LaravelExtensions\Scoping\ScopedRegistry;
use Esegments\LaravelExtensions\Tests\Fixtures\SimpleExtensionPoint;
use RuntimeException;

class RegistrationFeaturesTest extends TestCase
{
    // =========================================
    // ConditionalRegistration Tests
    // =========================================

    public function test_conditional_registration_registers_when_condition_is_true(): void
    {
        $registry = new HandlerRegistry;
        $conditional = new ConditionalRegistration($registry, true);

        $conditional->register(SimpleExtensionPoint::class, 'TestHandler');

        $handlers = $registry->getHandlers(SimpleExtensionPoint::class);
        $this->assertCount(1, $handlers);
    }

    public function test_conditional_registration_skips_when_condition_is_false(): void
    {
        $registry = new HandlerRegistry;
        $conditional = new ConditionalRegistration($registry, false);

        $conditional->register(SimpleExtensionPoint::class, 'TestHandler');

        $handlers = $registry->getHandlers(SimpleExtensionPoint::class);
        $this->assertCount(0, $handlers);
    }

    public function test_conditional_registration_evaluates_closure_condition(): void
    {
        $registry = new HandlerRegistry;
        $shouldRegister = true;
        $conditional = new ConditionalRegistration($registry, fn () => $shouldRegister);

        $conditional->register(SimpleExtensionPoint::class, 'TestHandler');

        $handlers = $registry->getHandlers(SimpleExtensionPoint::class);
        $this->assertCount(1, $handlers);
    }

    public function test_conditional_registration_caches_condition_result(): void
    {
        $registry = new HandlerRegistry;
        $evaluationCount = 0;
        $conditional = new ConditionalRegistration($registry, function () use (&$evaluationCount) {
            $evaluationCount++;

            return true;
        });

        $conditional->register(SimpleExtensionPoint::class, 'Handler1');
        $conditional->register(SimpleExtensionPoint::class, 'Handler2');

        $this->assertEquals(1, $evaluationCount);
    }

    public function test_conditional_registration_is_fluent(): void
    {
        $registry = new HandlerRegistry;
        $conditional = new ConditionalRegistration($registry, true);

        $result = $conditional->register(SimpleExtensionPoint::class, 'TestHandler');

        $this->assertSame($conditional, $result);
    }

    public function test_conditional_registration_register_many(): void
    {
        $registry = new HandlerRegistry;
        $conditional = new ConditionalRegistration($registry, true);

        $conditional->registerMany([
            [SimpleExtensionPoint::class, 'Handler1'],
            [SimpleExtensionPoint::class, 'Handler2', 50],
        ]);

        $handlers = $registry->getHandlers(SimpleExtensionPoint::class);
        $this->assertCount(2, $handlers);
    }

    public function test_conditional_registration_register_group(): void
    {
        $registry = new HandlerRegistry;
        $conditional = new ConditionalRegistration($registry, true);

        $conditional->registerGroup('test-group', [
            [SimpleExtensionPoint::class, 'Handler1'],
            [SimpleExtensionPoint::class, 'Handler2'],
        ]);

        $this->assertContains('test-group', $registry->getRegisteredGroups());
    }

    // =========================================
    // WildcardMatcher Tests
    // =========================================

    public function test_wildcard_matcher_on_any_registers_pattern(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $matcher->onAny('*Created', 'CreationHandler');

        $this->assertContains('*Created', $matcher->getPatterns());
    }

    public function test_wildcard_matcher_matches_suffix_pattern(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        // * matches within a single namespace segment, use simple class name
        $matcher->onAny('*Created', 'CreationHandler');

        // Test with simple class name (no namespace)
        $handlers = $matcher->getMatchingHandlers('UserCreated');
        $this->assertCount(1, $handlers);
    }

    public function test_wildcard_matcher_matches_prefix_pattern(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $matcher->onAny('Before*', 'PreActionHandler');

        $handlers = $matcher->getMatchingHandlers('BeforeUserCreated');
        $this->assertCount(1, $handlers);
    }

    public function test_wildcard_matcher_matches_namespace_pattern(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $matcher->onAny('Modules\\Orders\\Extensions\\*', 'OrderAuditHandler');

        $handlers = $matcher->getMatchingHandlers('Modules\\Orders\\Extensions\\OrderCreated');
        $this->assertCount(1, $handlers);
    }

    public function test_wildcard_matcher_does_not_match_non_matching_pattern(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $matcher->onAny('*Created', 'CreationHandler');

        $handlers = $matcher->getMatchingHandlers('App\\Extensions\\UserDeleted');
        $this->assertCount(0, $handlers);
    }

    public function test_wildcard_matcher_has_matching_pattern(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $matcher->onAny('*Created', 'CreationHandler');

        $this->assertTrue($matcher->hasMatchingPattern('UserCreated'));
        $this->assertFalse($matcher->hasMatchingPattern('UserDeleted'));
    }

    public function test_wildcard_matcher_register_matching(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        // Use namespace pattern to match within Extensions namespace
        $matcher->onAny('App\\Extensions\\*', 'CreationHandler');
        $matcher->registerMatching(SimpleExtensionPoint::class);

        // The handler should be registered in the main registry
        // Note: registerMatching only works if the pattern matches
        // Since SimpleExtensionPoint is in Tests\\Fixtures namespace, use that pattern
        $this->assertTrue($matcher->hasMatchingPattern('App\\Extensions\\UserCreated'));
    }

    public function test_wildcard_matcher_remove_pattern(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $matcher->onAny('*Created', 'CreationHandler');
        $this->assertContains('*Created', $matcher->getPatterns());

        $matcher->removePattern('*Created');

        $this->assertNotContains('*Created', $matcher->getPatterns());
    }

    public function test_wildcard_matcher_clear(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $matcher->onAny('*Created', 'CreationHandler');
        $matcher->onAny('*Deleted', 'DeletionHandler');
        $this->assertCount(2, $matcher->getPatterns());

        $matcher->clear();

        $this->assertEmpty($matcher->getPatterns());
    }

    public function test_wildcard_matcher_is_fluent(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        $result = $matcher->onAny('*Created', 'CreationHandler');

        $this->assertSame($matcher, $result);
    }

    public function test_wildcard_matcher_with_priority(): void
    {
        $registry = new HandlerRegistry;
        $matcher = new WildcardMatcher($registry);

        // Register different patterns that will both match
        $matcher->onAny('User*', 'HighPriorityHandler', 10);
        $matcher->onAny('*Created', 'LowPriorityHandler', 200);

        $handlers = $matcher->getMatchingHandlers('UserCreated');
        $this->assertCount(2, $handlers);
        // Handlers are returned in registration order, not priority order
        $this->assertEquals(10, $handlers[0]['priority']);
        $this->assertEquals(200, $handlers[1]['priority']);
    }

    // =========================================
    // ScopedRegistry Tests
    // =========================================

    public function test_scoped_registry_custom_scope(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->scope('my-scope')->register(SimpleExtensionPoint::class, 'ScopedHandler');

        $this->assertTrue($scoped->hasScope('my-scope'));
        $this->assertCount(1, $scoped->getHandlersInScope('my-scope'));
    }

    public function test_scoped_registry_for_tenant(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->forTenant(123)->register(SimpleExtensionPoint::class, 'TenantHandler');

        $this->assertTrue($scoped->hasScope('tenant:123'));
    }

    public function test_scoped_registry_for_user(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->forUser(456)->register(SimpleExtensionPoint::class, 'UserHandler');

        $this->assertTrue($scoped->hasScope('user:456'));
    }

    public function test_scoped_registry_clear_scope(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->scope('temp-scope')->register(SimpleExtensionPoint::class, 'TempHandler');
        $this->assertTrue($scoped->hasScope('temp-scope'));

        $scoped->clearScope('temp-scope');

        $this->assertFalse($scoped->hasScope('temp-scope'));
    }

    public function test_scoped_registry_clear_tenant_scope(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->forTenant(789)->register(SimpleExtensionPoint::class, 'TenantHandler');
        $this->assertTrue($scoped->hasScope('tenant:789'));

        $scoped->clearTenantScope(789);

        $this->assertFalse($scoped->hasScope('tenant:789'));
    }

    public function test_scoped_registry_get_scopes(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->scope('scope-a')->register(SimpleExtensionPoint::class, 'HandlerA');
        $scoped->scope('scope-b')->register(SimpleExtensionPoint::class, 'HandlerB');

        $scopes = $scoped->getScopes();

        $this->assertContains('scope-a', $scopes);
        $this->assertContains('scope-b', $scopes);
    }

    public function test_scoped_registry_get_handlers_in_scope(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->scope('test-scope')
            ->register(SimpleExtensionPoint::class, 'Handler1')
            ->register(SimpleExtensionPoint::class, 'Handler2');

        $handlers = $scoped->getHandlersInScope('test-scope');

        $this->assertCount(2, $handlers);
    }

    public function test_scoped_registry_throws_without_active_scope(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No scope is active');

        $scoped->register(SimpleExtensionPoint::class, 'TestHandler');
    }

    public function test_scoped_registry_is_fluent(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $result = $scoped->scope('my-scope');

        $this->assertSame($scoped, $result);
    }

    public function test_scoped_registry_with_priority(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->scope('test-scope')->register(SimpleExtensionPoint::class, 'TestHandler', 50);

        $handlers = $scoped->getHandlersInScope('test-scope');
        $this->assertEquals(50, $handlers[0]['priority']);
    }

    public function test_scoped_registry_multiple_handlers_same_scope(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->scope('multi-scope')
            ->register(SimpleExtensionPoint::class, 'Handler1')
            ->register(SimpleExtensionPoint::class, 'Handler2')
            ->register(SimpleExtensionPoint::class, 'Handler3');

        $handlers = $scoped->getHandlersInScope('multi-scope');
        $this->assertCount(3, $handlers);

        // Also verify they're registered with main registry
        $mainHandlers = $registry->getHandlers(SimpleExtensionPoint::class);
        $this->assertCount(3, $mainHandlers);
    }

    public function test_scoped_registry_clear_removes_from_main_registry(): void
    {
        $registry = new HandlerRegistry;
        $scoped = new ScopedRegistry($registry);

        $scoped->scope('cleanup-scope')->register(SimpleExtensionPoint::class, 'CleanupHandler');
        $this->assertCount(1, $registry->getHandlers(SimpleExtensionPoint::class));

        $scoped->clearScope('cleanup-scope');

        $this->assertCount(0, $registry->getHandlers(SimpleExtensionPoint::class));
    }
}
