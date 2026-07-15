<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Builders;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Tests\Fixtures\Channels\TestChannel;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Repositories\RecurringTaskRepository;
use AndyDefer\Task\Repositories\UniqueTaskRepository;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class NotifiableBuilderTest extends TestCase
{
    use DatabaseMigrations;

    private NotificationServiceInterface $service;

    private NotificationRepository $repository;

    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runDatabaseMigrations();

        $this->repository = app(NotificationRepository::class);
        $this->service = app(NotificationServiceInterface::class);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_secondary' => 'admin@example.com',
            'phone' => '+33123456789',
        ]);
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function getNotificationsForUser(TestUser $user)
    {
        $filter = NotificationFilterRecord::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $user->getKey(),
        ]);

        return $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );
    }

    private function getNotificationsByDestination(TestUser $user, string $destination)
    {
        return $this->repository->findBy(
            new FindByRecord(filters: NotificationFilterRecord::from([
                'notifiable_type' => TestUser::class,
                'notifiable_id' => $user->getKey(),
            ]))
        )->filter(function ($notification) use ($destination) {
            return $notification->destination === $destination;
        });
    }

    private function getNotifiableFromBuilder(NotifiableBuilder $builder)
    {
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildNotifiable');
        $method->setAccessible(true);

        return $method->invoke($builder);
    }

    private function getOptionsFromBuilder(NotifiableBuilder $builder): ?SendOptions
    {
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('options');
        $property->setAccessible(true);

        return $property->getValue($builder);
    }

    // ==================== TESTS: Basic Creation ====================

    public function test_create_builder(): void
    {
        $builder = NotifiableBuilder::create();

        $this->assertInstanceOf(NotifiableBuilder::class, $builder);
    }

    public function test_create_builder_with_service(): void
    {
        $builder = new NotifiableBuilder($this->service);

        $this->assertInstanceOf(NotifiableBuilder::class, $builder);
    }

    // ==================== TESTS: Channel Configuration ====================

    public function test_to_sets_destination_for_channel(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test')
            ->body('Test body');

        $notifiable = $this->getNotifiableFromBuilder($builder);
        $routes = $notifiable->getNotificationChannels();

        $this->assertCount(1, $routes);
        $this->assertTrue($routes->hasChannel(MailChannel::class));
        $this->assertTrue($routes->hasDestination('john@example.com'));
    }

    public function test_to_sets_multiple_destinations_for_channel(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, ['john@example.com', 'admin@example.com'])
            ->subject('Test')
            ->body('Test body');

        $notifiable = $this->getNotifiableFromBuilder($builder);
        $routes = $notifiable->getNotificationChannels();

        $this->assertCount(2, $routes);
        $this->assertTrue($routes->hasChannel(MailChannel::class));
        $this->assertTrue($routes->hasDestination('john@example.com'));
        $this->assertTrue($routes->hasDestination('admin@example.com'));
    }

    public function test_to_handles_multiple_channels(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->to(TestChannel::class, '+33123456789')
            ->subject('Test')
            ->body('Test body');

        $notifiable = $this->getNotifiableFromBuilder($builder);
        $routes = $notifiable->getNotificationChannels();

        $this->assertCount(2, $routes);
        $this->assertTrue($routes->hasChannel(MailChannel::class));
        $this->assertTrue($routes->hasChannel(TestChannel::class));
        $this->assertTrue($routes->hasDestination('john@example.com'));
        $this->assertTrue($routes->hasDestination('+33123456789'));
    }

    public function test_to_overwrites_existing_channel_destinations(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'old@example.com')
            ->to(MailChannel::class, 'new@example.com')
            ->subject('Test')
            ->body('Test body');

        $notifiable = $this->getNotifiableFromBuilder($builder);
        $routes = $notifiable->getNotificationChannels();

        // ✅ Devrait avoir 1 destination (overwrite)
        $this->assertCount(1, $routes);
        $this->assertTrue($routes->hasDestination('new@example.com'));
        $this->assertFalse($routes->hasDestination('old@example.com'));
    }

    // ==================== TESTS: Message Configuration ====================

    public function test_body_sets_message_body(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test Subject')
            ->body('Test Body Content');

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('body');
        $property->setAccessible(true);
        $body = $property->getValue($builder);

        $this->assertEquals('Test Body Content', $body->getValue());
    }

    public function test_subject_sets_message_subject(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test Subject')
            ->body('Test body');

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('subject');
        $property->setAccessible(true);
        $subject = $property->getValue($builder);

        $this->assertEquals('Test Subject', $subject->getValue());
    }

    public function test_type_sets_message_type(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test')
            ->body('Test body')
            ->type('welcome');

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('type');
        $property->setAccessible(true);

        $this->assertEquals('welcome', $property->getValue($builder));
    }

    public function test_data_sets_message_data(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test')
            ->body('Test body')
            ->data(['user_id' => 123, 'order_id' => 456]);

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('data');
        $property->setAccessible(true);
        $data = $property->getValue($builder);

        $this->assertEquals(123, $data->get('user_id'));
        $this->assertEquals(456, $data->get('order_id'));
    }

    public function test_body_throws_exception_when_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Message body is required. Call body() first.');

        NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test Subject')
            ->sendNow();
    }

    public function test_subject_throws_exception_when_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Message subject is required. Call subject() first.');

        NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->body('Test body')
            ->sendNow();
    }

    // ==================== TESTS: Options Configuration ====================

    public function test_limit_sets_limit_per_channel(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, ['john@example.com', 'admin@example.com'])
            ->subject('Test')
            ->body('Test body')
            ->limit(1);

        $options = $this->getOptionsFromBuilder($builder);

        $this->assertNotNull($options);
        $this->assertEquals(1, $options->limitPerChannel);
    }

    public function test_filter_adds_destination_filter(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, ['john@example.com', 'admin@example.com'])
            ->subject('Test')
            ->body('Test body')
            ->filter(MailChannel::class, 'john@example.com');

        $options = $this->getOptionsFromBuilder($builder);

        $this->assertNotNull($options);
        $filters = $options->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filters);
        $this->assertContains('john@example.com', $filters[MailChannel::class]);
    }

    public function test_filters_sets_all_filters(): void
    {
        $filters = [
            MailChannel::class => ['john@example.com'],
            TestChannel::class => ['+33123456789'],
        ];

        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->to(TestChannel::class, '+33123456789')
            ->subject('Test')
            ->body('Test body')
            ->filters($filters);

        $options = $this->getOptionsFromBuilder($builder);

        $this->assertNotNull($options);
        $filterArray = $options->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filterArray);
        $this->assertArrayHasKey(TestChannel::class, $filterArray);
        $this->assertContains('john@example.com', $filterArray[MailChannel::class]);
        $this->assertContains('+33123456789', $filterArray[TestChannel::class]);
    }

    public function test_options_sets_send_options(): void
    {
        $sendOptions = SendOptions::init()
            ->withLimitPerChannel(2)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test')
            ->body('Test body')
            ->options($sendOptions);

        $options = $this->getOptionsFromBuilder($builder);

        $this->assertNotNull($options);
        $this->assertEquals(2, $options->limitPerChannel);
        $filters = $options->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filters);
    }

    // ==================== TESTS: Metadata Configuration ====================

    public function test_metadata_sets_metadata_for_channel(): void
    {
        $metadata = new StrictDataObject([
            'priority' => 'high',
            'name' => 'John Doe',
        ]);

        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test')
            ->body('Test body')
            ->metadata(MailChannel::class, $metadata);

        $notifiable = $this->getNotifiableFromBuilder($builder);
        $routes = $notifiable->getNotificationChannels();

        $route = $routes->first();
        $this->assertNotNull($route);
        $this->assertEquals('high', $route->getMetadata()->get('priority'));
        $this->assertEquals('John Doe', $route->getMetadata()->get('name'));
    }

    public function test_metadata_all_sets_metadata_for_all_channels(): void
    {
        $metadata = new StrictDataObject([
            'source' => 'api',
            'version' => '1.0',
        ]);

        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->to(TestChannel::class, '+33123456789')
            ->subject('Test')
            ->body('Test body')
            ->metadataAll($metadata);

        $notifiable = $this->getNotifiableFromBuilder($builder);
        $routes = $notifiable->getNotificationChannels();

        foreach ($routes as $route) {
            $this->assertEquals('api', $route->getMetadata()->get('source'));
            $this->assertEquals('1.0', $route->getMetadata()->get('version'));
        }
    }

    // ==================== TESTS: Tracing Configuration ====================

    public function test_as_sets_morph_class_and_key(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test')
            ->body('Test body')
            ->as('external_user', 12345);

        $notifiable = $this->getNotifiableFromBuilder($builder);

        $this->assertEquals('external_user', $notifiable->getMorphClass());
        $this->assertEquals(12345, $notifiable->getKey());
    }

    // ==================== TESTS: Send Methods ====================

    public function test_send_now_sends_notification(): void
    {
        // ✅ Utiliser TestChannel (toujours disponible)
        $results = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test Subject')
            ->body('Test Body')
            ->sendNow();

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertTrue($results->allSuccess());
        $this->assertCount(1, $results);
    }

    public function test_send_now_with_record(): void
    {
        $record = new SendNowRecord;

        $results = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test')
            ->body('Test body')
            ->sendNow($record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertTrue($results->allSuccess());
    }

    public function test_send_later_schedules_notification(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $alias = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test Subject')
            ->body('Test Body')
            ->sendLater(300);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = app(UniqueTaskRepository::class)->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(
            $frozenNow->copy()->addSeconds(300)->toIso8601String(),
            $task->getScheduledAt()->getValue()
        );
    }

    public function test_send_later_with_custom_delay(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $alias = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test')
            ->body('Test body')
            ->sendLater(600);

        $task = app(UniqueTaskRepository::class)->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(
            $frozenNow->copy()->addSeconds(600)->toIso8601String(),
            $task->getScheduledAt()->getValue()
        );
    }

    public function test_send_at_schedules_notification_at_specific_time(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String());

        $alias = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test')
            ->body('Test body')
            ->sendAt($scheduledAt);

        $task = app(UniqueTaskRepository::class)->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(
            $scheduledAt->getValue(),
            $task->getScheduledAt()->getValue()
        );
    }

    public function test_send_recurring_schedules_recurring_notification(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $startAt = new NotificationDateTimeVO($frozenNow->toIso8601String());

        $alias = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test')
            ->body('Test body')
            ->sendRecurring(3600, $startAt);

        $task = app(RecurringTaskRepository::class)->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(3600, $task->getIntervalSeconds()->getValue());
    }

    public function test_send_recurring_with_end_at(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $startAt = new NotificationDateTimeVO($frozenNow->toIso8601String());
        $endAt = new NotificationDateTimeVO($frozenNow->copy()->addDays(7)->toIso8601String());

        $alias = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test')
            ->body('Test body')
            ->sendRecurring(3600, $startAt, $endAt);

        $task = app(RecurringTaskRepository::class)->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals($endAt->getValue(), $task->getEndAt()?->getValue());
    }

    // ==================== TESTS: Send with Options ====================

    public function test_send_now_with_limit(): void
    {
        $results = NotifiableBuilder::create()
            ->to(TestChannel::class, ['test_destination', 'test_destination_2'])
            ->subject('Test')
            ->body('Test body')
            ->limit(1)
            ->sendNow();

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_send_now_with_filter(): void
    {
        $results = NotifiableBuilder::create()
            ->to(TestChannel::class, ['test_destination', 'test_destination_2'])
            ->subject('Test')
            ->body('Test body')
            ->filter(TestChannel::class, 'test_destination')
            ->sendNow();

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertEquals('test_destination', $result->destination);
    }

    public function test_send_now_with_filters_and_limit(): void
    {
        $results = NotifiableBuilder::create()
            ->to(TestChannel::class, ['test_destination', 'test_destination_2', 'test_destination_3'])
            ->subject('Test')
            ->body('Test body')
            ->filter(TestChannel::class, ['test_destination', 'test_destination_2'])
            ->limit(1)
            ->sendNow();

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_send_later_with_options(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $alias = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test')
            ->body('Test body')
            ->limit(1)
            ->filter(TestChannel::class, 'test_destination')
            ->sendLater(300);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = app(UniqueTaskRepository::class)->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(
            $frozenNow->copy()->addSeconds(300)->toIso8601String(),
            $task->getScheduledAt()->getValue()
        );
    }

    // ==================== TESTS: Reset ====================

    public function test_reset_clears_builder_state(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(MailChannel::class, 'john@example.com')
            ->subject('Test')
            ->body('Test body')
            ->limit(1)
            ->as('external', 123)
            ->type('welcome')
            ->data(['key' => 'value']);

        $builder->reset();

        $notifiable = $this->getNotifiableFromBuilder($builder);
        $routes = $notifiable->getNotificationChannels();

        $this->assertCount(0, $routes);
        $this->assertEquals('direct', $notifiable->getMorphClass());
        $this->assertEquals(0, $notifiable->getKey());

        $options = $this->getOptionsFromBuilder($builder);
        $this->assertNull($options);

        $this->expectException(\RuntimeException::class);
        $builder->sendNow();
    }

    public function test_reset_after_send(): void
    {
        $builder = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Test')
            ->body('Test body')
            ->limit(1);

        $builder->sendNow();

        // ✅ Options should be auto-reset after send
        $options = $this->getOptionsFromBuilder($builder);
        $this->assertNull($options);
    }

    // ==================== TESTS: Edge Cases ====================

    public function test_to_with_empty_destination_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination cannot be empty.');

        NotifiableBuilder::create()
            ->to(MailChannel::class, '')
            ->subject('Test')
            ->body('Test body')
            ->sendNow();
    }

    public function test_to_with_empty_array_destination_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination cannot be empty.');

        NotifiableBuilder::create()
            ->to(MailChannel::class, [])
            ->subject('Test')
            ->body('Test body')
            ->sendNow();
    }

    public function test_chaining_all_methods(): void
    {
        $results = NotifiableBuilder::create()
            ->to(TestChannel::class, 'test_destination')
            ->subject('Chained Test')
            ->body('Chained body content')
            ->type('test')
            ->data(['key' => 'value'])
            ->limit(1)
            ->filter(TestChannel::class, 'test_destination')
            ->as('chained', 999)
            ->sendNow();

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertTrue($results->allSuccess());
        $this->assertCount(1, $results);
    }

    public function test_send_now_with_multiple_recipients(): void
    {
        $results = NotifiableBuilder::create()
            ->to(TestChannel::class, [
                'test_destination_1',
                'test_destination_2',
                'test_destination_3',
            ])
            ->subject('Bulk Test')
            ->body('Bulk body content')
            ->sendNow();

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(3, $results);
        $this->assertTrue($results->allSuccess());

        $destinations = $results->map(fn ($r) => $r->destination)->toArray();
        $this->assertContains('test_destination_1', $destinations);
        $this->assertContains('test_destination_2', $destinations);
        $this->assertContains('test_destination_3', $destinations);
    }
}
