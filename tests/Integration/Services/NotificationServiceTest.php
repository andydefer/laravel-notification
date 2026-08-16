<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Services;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\Records\SessionStatsRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Channels\TestChannel;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestEmptyChannel;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageViewBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationStatsVO;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Repositories\RecurringTaskRepository;
use AndyDefer\Task\Repositories\UniqueTaskRepository;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Ramsey\Uuid\Uuid;

final class NotificationServiceTest extends TestCase
{
    use DatabaseMigrations;

    private NotificationServiceInterface $service;

    private TestUser $user;

    private NotificationMessageVO $message;

    private NotificationRepository $repository;

    private UniqueTaskRepository $uniqueTaskRepository;

    private RecurringTaskRepository $recurringTaskRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runDatabaseMigrations();

        // ✅ Ajout du namespace pour les vues de test (fait une seule fois)
        View::addNamespace('test', __DIR__.'/../../Fixtures/resources/views');

        $this->repository = app(NotificationRepository::class);
        $this->uniqueTaskRepository = app(UniqueTaskRepository::class);
        $this->recurringTaskRepository = app(RecurringTaskRepository::class);

        $this->service = new NotificationService(
            notificationRepository: $this->repository,
            senderProcessor: app(NotificationSenderProcessor::class),
            uniqueTaskService: app(UniqueTaskServiceInterface::class),
            recurringTaskService: app(RecurringTaskServiceInterface::class),
            logger: app(LoggerInterface::class),
            hydration: app(HydrationService::class),
        );

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_secondary' => 'admin@example.com',
            'phone' => '+33123456789',
        ]);

        $this->message = new NotificationMessageVO(
            body: new MessageBodyVO('Test message'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test',
            data: new StrictDataObject(['key' => 'value'])
        );
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    // ==================== TESTS: sendNow ====================

    public function test_send_now_with_all_channels(): void
    {
        $record = new SendNowRecord;

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(5, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
        }

        $count = $this->repository->countByNotifiable($this->user);
        $this->assertEquals(5, $count);
    }

    public function test_send_now_with_specific_channels(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(channels: $channels);

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $this->assertEquals(MailChannel::class, $result->channel->getValue());
        }

        $count = $this->repository->countByNotifiable($this->user);
        $this->assertEquals(2, $count);
    }

    public function test_send_now_with_limit_per_channel(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_send_now_throws_exception_when_no_channels_available(): void
    {
        $user = TestEmptyChannel::create(['name' => 'No Channels']);

        $record = new SendNowRecord;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No available channels for notifiable');

        $this->service->sendNow($user, $this->message, $record);
    }

    // ==================== TESTS: sendNow with Options ====================

    public function test_send_now_with_options_single_channel(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $this->assertEquals(MailChannel::class, $result->channel->getValue());
        }
    }

    public function test_send_now_with_options_multiple_channels(): void
    {
        $options = SendOptions::init()
            ->withChannels([MailChannel::class, TestChannel::class]);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(4, $results);

        $channels = $results->map(fn ($r) => $r->channel->getValue())->toArray();
        $this->assertContains(MailChannel::class, $channels);
        $this->assertContains(TestChannel::class, $channels);
    }

    public function test_send_now_with_options_limit_per_channel(): void
    {
        $options = SendOptions::init()
            ->withChannels([MailChannel::class, TestChannel::class])
            ->withLimitPerChannel(1);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);
    }

    public function test_send_now_with_options_destination_filter_single(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals('john@example.com', $result->destination);
    }

    public function test_send_now_with_options_destination_filter_multiple(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, [
                'john@example.com',
                'admin@example.com',
            ]);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        $destinations = $results->map(fn ($r) => $r->destination)->toArray();
        $this->assertContains('john@example.com', $destinations);
        $this->assertContains('admin@example.com', $destinations);
    }

    public function test_send_now_with_options_destination_filter_filters_out_non_matching(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, 'non-matching@example.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No routes after applying destination filters for notifiable');

        $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);
    }

    public function test_send_now_with_options_multiple_filters(): void
    {
        $options = SendOptions::init()
            ->withChannels([MailChannel::class, TestChannel::class])
            ->withDestinationFilter(MailChannel::class, 'john@example.com')
            ->withDestinationFilter(TestChannel::class, '+33123456789');

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        foreach ($results as $result) {
            if ($result->channel->getValue() === MailChannel::class) {
                $this->assertEquals('john@example.com', $result->destination);
            } elseif ($result->channel->getValue() === TestChannel::class) {
                $this->assertEquals('+33123456789', $result->destination);
            }
        }
    }

    public function test_send_now_with_options_combined_with_record(): void
    {
        $record = new SendNowRecord(
            channels: new FqcnChannelCollection,
            limit_per_channel: null
        );

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withLimitPerChannel(1)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
    }

    public function test_send_now_with_options_auto_reset_after_send(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class);

        $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $results = $this->service->sendNow($this->user, $this->message);

        $this->assertCount(5, $results);
    }

    public function test_reset_options_manually(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class);

        $this->service->withOptions($options);
        $this->service->resetOptions();

        $results = $this->service->sendNow($this->user, $this->message);

        $this->assertCount(5, $results);
    }

    // ==================== TESTS: sendLater with Options ====================

    public function test_send_later_with_options(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $alias = $this->service
            ->withOptions($options)
            ->sendLater($this->user, $this->message, new SendLaterRecord(delay_seconds: 300));

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(MailChannel::class, $payload->get('channels'));
        $this->assertEquals('john@example.com', $payload->get('destination_filter')[MailChannel::class][0]);
    }

    public function test_send_later_with_options_and_record_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(TestChannel::class));

        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: $channels
        );

        $options = (new SendOptions)
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $alias = $this->service
            ->withOptions($options)
            ->sendLater($this->user, $this->message, $record);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $payload = $task->getPayload();

        $this->assertContains(MailChannel::class, $payload->get('channels'));
        $this->assertEquals('john@example.com', $payload->get('destination_filter')[MailChannel::class][0]);
    }

    // ==================== TESTS: sendAt with Options ====================

    public function test_send_at_with_options(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String());

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $record = new SendAtRecord(scheduled_at: $scheduledAt);

        $alias = $this->service
            ->withOptions($options)
            ->sendAt($this->user, $this->message, $record);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $payload = $task->getPayload();

        $this->assertContains(MailChannel::class, $payload->get('channels'));
        $this->assertEquals('john@example.com', $payload->get('destination_filter')[MailChannel::class][0]);
    }

    // ==================== TESTS: sendRecurring with Options ====================

    public function test_send_recurring_with_options(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        $alias = $this->service
            ->withOptions($options)
            ->sendRecurring($this->user, $this->message, $record);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $payload = $task->getPayload();

        $this->assertContains(MailChannel::class, $payload->get('channels'));
        $this->assertEquals('john@example.com', $payload->get('destination_filter')[MailChannel::class][0]);
    }

    public function test_send_recurring_with_options_and_record_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(TestChannel::class));

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            channels: $channels
        );

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, 'john@example.com');

        $alias = $this->service
            ->withOptions($options)
            ->sendRecurring($this->user, $this->message, $record);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $payload = $task->getPayload();

        $this->assertContains(MailChannel::class, $payload->get('channels'));
        $this->assertEquals('john@example.com', $payload->get('destination_filter')[MailChannel::class][0]);
    }

    // ==================== TESTS: sendLater ====================

    public function test_send_later_schedules_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(delay_seconds: 300);

        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(SendDelayedNotificationTask::class, $task->getFqcn());
    }

    public function test_send_later_throws_exception_when_delay_zero(): void
    {
        $record = new SendLaterRecord(delay_seconds: 0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delay seconds must be greater than 0.');

        $this->service->sendLater($this->user, $this->message, $record);
    }

    // ==================== TESTS: sendAt ====================

    public function test_send_at_schedules_task_at_specific_time(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String());

        $record = new SendAtRecord(scheduled_at: $scheduledAt);

        $alias = $this->service->sendAt($this->user, $this->message, $record);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(
            $frozenNow->copy()->addHours(2)->format('Y-m-d H:i:s'),
            $task->getScheduledAt()->getValue()
        );
    }

    public function test_send_at_throws_exception_when_date_in_past(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String());

        $record = new SendAtRecord(scheduled_at: $scheduledAt);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scheduled date must be in the future.');

        $this->service->sendAt($this->user, $this->message, $record);
    }

    // ==================== TESTS: sendRecurring ====================

    public function test_send_recurring_schedules_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(SendRecurringNotificationTask::class, $task->getFqcn());
        $this->assertEquals(3600, $task->getIntervalSeconds()->getValue());
    }

    public function test_send_recurring_throws_exception_when_interval_zero(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 0,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval seconds must be at least 1 second.');

        $this->service->sendRecurring($this->user, $this->message, $record);
    }

    // ==================== TESTS: Task Management ====================

    public function test_cancel_unique_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(delay_seconds: 300);
        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $result = $this->service->cancel($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_cancel_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $result = $this->service->cancel($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_cancel_non_existing_task_returns_false(): void
    {
        $result = $this->service->cancel('unique@'.Uuid::uuid4()->toString());

        $this->assertFalse($result);
    }

    public function test_pause_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $result = $this->service->pause($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_resume_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->service->pause($alias->getValue());
        $result = $this->service->resume($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_change_interval_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $result = $this->service->changeInterval($alias->getValue(), 7200);

        $this->assertTrue($result);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(7200, $task->getIntervalSeconds()->getValue());
    }

    // ==================== TESTS: Statistics ====================

    public function test_get_stats(): void
    {
        $record = new SendNowRecord;
        $this->service->sendNow($this->user, $this->message, $record);

        $stats = $this->service->getStats($this->user);

        $this->assertInstanceOf(NotificationStatsVO::class, $stats);
        $this->assertEquals(5, $stats->total);
        $this->assertEquals(5, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(100, $stats->success_rate);
    }

    public function test_get_stats_with_no_notifications(): void
    {
        $stats = $this->service->getStats($this->user);

        $this->assertInstanceOf(NotificationStatsVO::class, $stats);
        $this->assertEquals(0, $stats->total);
        $this->assertEquals(0, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->success_rate);
    }

    public function test_get_session_stats(): void
    {
        $record = new SendNowRecord;
        $this->service->sendNow($this->user, $this->message, $record);

        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        $notifications = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $sessionId = $notifications->first()->getSessionId();

        $stats = $this->service->getSessionStats($sessionId);

        $this->assertInstanceOf(SessionStatsRecord::class, $stats);
        $this->assertEquals($sessionId, $stats->session_id);
        $this->assertEquals(5, $stats->total);
        $this->assertEquals(5, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->pending);
    }

    // ============================================================
    // NEW TESTS: MessageViewBodyVO
    // ============================================================

    public function test_message_view_body_vo_renders_html_view(): void
    {
        // Arrange : Créer un body avec une vue HTML via from()
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        // Act : Récupérer la valeur (rendu automatique)
        $rendered = $body->getValue();

        // Assert : Vérifier que la vue a été rendue correctement
        $this->assertStringContainsString('<h1>Bienvenue John Doe</h1>', $rendered);
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_message_view_body_vo_renders_plain_text_view_for_sms(): void
    {
        // Arrange : Créer un body avec une vue en texte brut via from()
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'Jane Smith'],
            'plainText' => true,
        ]);

        // Act : Récupérer la valeur (rendu automatique)
        $rendered = $body->getValue();

        // Assert : Vérifier que la vue a été rendue en texte brut
        $this->assertStringContainsString('Bienvenue Jane Smith', $rendered);
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $rendered);
        $this->assertStringNotContainsString('<h1>', $rendered);
        $this->assertStringNotContainsString('</h1>', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_message_view_body_vo_with_merge_data(): void
    {
        // Arrange : Créer un body avec des données et des données fusionnées via from()
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'mergeData' => ['signature' => 'L\'équipe Afya'],
        ]);

        // Act : Récupérer la valeur
        $rendered = $body->getValue();

        // Assert : Vérifier que les données fusionnées sont disponibles
        $this->assertStringContainsString('John Doe', $rendered);
        // ✅ Blade échappe les apostrophes → chercher la version HTML échappée
        $this->assertStringContainsString('L&#039;équipe Afya', $rendered);
    }

    public function test_message_view_body_vo_with_data_immutable(): void
    {
        // Arrange : Créer un body initial via from()
        $originalBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        // Act : Ajouter des données (créé une nouvelle instance)
        $newBody = $originalBody->withData(['extra' => 'value']);

        // Assert : Vérifier que l'original n'a pas été modifié
        $this->assertNotSame($originalBody, $newBody);
        $this->assertEquals('John Doe', $originalBody->getData()->get('name'));
        $this->assertFalse($originalBody->getData()->has('extra'));
        $this->assertEquals('John Doe', $newBody->getData()->get('name'));
        $this->assertEquals('value', $newBody->getData()->get('extra'));
    }

    public function test_message_view_body_vo_can_be_used_as_message_body(): void
    {
        // Arrange : Créer un body avec vue via from()
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        // Act : Créer un message avec le body (MessageBodyVO attendu)
        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        // Assert : Vérifier que le message a bien le corps rendu
        $this->assertInstanceOf(MessageBodyVO::class, $message->body);
        $this->assertInstanceOf(MessageViewBodyVO::class, $message->body);
        $this->assertStringContainsString('Bienvenue John Doe', $message->body->getValue());
    }

    public function test_message_view_body_vo_plain_text_renders_for_sms_channel(): void
    {
        // Arrange : Créer un body en mode plainText via from()
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'plainText' => true,
        ]);

        // Act : Créer un message
        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('SMS Notification'),
            type: 'sms'
        );

        // Assert : Vérifier que c'est bien du texte brut
        $this->assertTrue($viewBody->isPlainText());
        $this->assertStringContainsString('Bienvenue John Doe', $message->body->getValue());
        $this->assertStringNotContainsString('<h1>', $message->body->getValue());
    }

    public function test_message_view_body_vo_html_helper(): void
    {
        // Act : Utiliser le helper html()
        $body = MessageViewBodyVO::html(
            view: 'test::welcome',
            data: ['name' => 'John Doe']
        );

        // Assert : Vérifier que c'est bien du HTML
        $this->assertFalse($body->isPlainText());
        $this->assertStringContainsString('<h1>', $body->getValue());
    }

    public function test_message_view_body_vo_plain_helper(): void
    {
        // Act : Utiliser le helper plain()
        $body = MessageViewBodyVO::plain(
            view: 'test::welcome',
            data: ['name' => 'John Doe']
        );

        // Assert : Vérifier que c'est bien du texte brut
        $this->assertTrue($body->isPlainText());
        $this->assertStringContainsString('Bienvenue John Doe', $body->getValue());
        $this->assertStringNotContainsString('<h1>', $body->getValue());
    }

    public function test_message_view_body_vo_as_html_conversion(): void
    {
        // Arrange : Créer un body en plainText via from()
        $plainBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'plainText' => true,
        ]);

        // Act : Convertir en HTML
        $htmlBody = $plainBody->asHtml();

        // Assert : Vérifier la conversion (immuable)
        $this->assertTrue($plainBody->isPlainText());
        $this->assertFalse($htmlBody->isPlainText());
        $this->assertNotSame($plainBody, $htmlBody);
    }

    public function test_message_view_body_vo_as_plain_text_conversion(): void
    {
        // Arrange : Créer un body en HTML via from()
        $htmlBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        // Act : Convertir en plainText
        $plainBody = $htmlBody->asPlainText();

        // Assert : Vérifier la conversion (immuable)
        $this->assertFalse($htmlBody->isPlainText());
        $this->assertTrue($plainBody->isPlainText());
        $this->assertNotSame($htmlBody, $plainBody);
    }

    public function test_message_view_body_vo_chained_methods(): void
    {
        // Act : Chaînage de méthodes (immuable) - tout via from()
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ])
            ->withData(['extra' => 'value'])
            ->asPlainText()
            ->withMergeData(['signature' => 'Team']);

        // Assert : Vérifier que tout a été appliqué
        $this->assertTrue($body->isPlainText());
        $this->assertEquals('John Doe', $body->getData()->get('name'));
        $this->assertEquals('value', $body->getData()->get('extra'));
        $this->assertEquals('Team', $body->getMergeData()->get('signature'));
        $this->assertStringContainsString('John Doe', $body->getValue());
        $this->assertStringNotContainsString('<h1>', $body->getValue());
        $this->assertStringContainsString('Team', $body->getValue());
    }

    public function test_message_view_body_vo_from_array_hydration(): void
    {
        // Act : Hydrater depuis un tableau
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'plainText' => false,
        ]);

        // Assert : Vérifier l'hydratation
        $this->assertInstanceOf(MessageViewBodyVO::class, $body);
        $this->assertEquals('test::welcome', $body->getView());
        $this->assertEquals('John Doe', $body->getData()->get('name'));
        $this->assertFalse($body->isPlainText());
        $this->assertStringContainsString('Bienvenue John Doe', $body->getValue());
    }

    public function test_message_view_body_vo_from_array_with_merge_data(): void
    {
        // Act : Hydrater depuis un tableau avec mergeData
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'mergeData' => ['signature' => 'L\'équipe Afya'],
            'plainText' => true,
        ]);

        // Assert : Vérifier l'hydratation
        $this->assertTrue($body->isPlainText());
        $this->assertEquals('John Doe', $body->getData()->get('name'));
        $this->assertEquals('L\'équipe Afya', $body->getMergeData()->get('signature'));
        $this->assertStringContainsString('John Doe', $body->getValue());
        // ✅ En mode plainText, l'apostrophe n'est pas échappée
        $this->assertStringContainsString('L\'équipe Afya', $body->getValue());
        $this->assertStringNotContainsString('<h1>', $body->getValue());
    }

    public function test_send_now_with_message_view_body_vo(): void
    {
        // Arrange : Créer un body avec vue via from()
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
        ]);

        // Arrange : Créer un message avec le body
        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Welcome Email'),
            type: 'welcome'
        );

        // Arrange : Configurer l'envoi
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        // Act : Envoyer la notification
        $results = $this->service->sendNow($this->user, $message, $record);

        // Assert : Vérifier que l'envoi a réussi
        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $this->assertEquals(MailChannel::class, $result->channel->getValue());
        }

        // Assert : Vérifier que la notification a été persistée avec le corps rendu
        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        $notifications = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(1, $notifications);

        $notification = $notifications->first();

        // ✅ Utilisation de getBody() qui retourne une string
        $this->assertStringContainsString('Bienvenue '.$this->user->name, $notification->getBody());
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $notification->getBody());
    }

    public function test_send_now_with_message_view_body_vo_plain_text(): void
    {
        // Arrange : Créer un body en mode plainText via from()
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
            'plainText' => true,
        ]);

        // Arrange : Créer un message
        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('SMS Welcome'),
            type: 'sms_welcome'
        );

        // Arrange : Configurer l'envoi pour SMS
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(TestChannel::class));

        $record = new SendNowRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        // Act : Envoyer la notification
        $results = $this->service->sendNow($this->user, $message, $record);

        // Assert : Vérifier que l'envoi a réussi
        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
        }

        // Assert : Vérifier que le corps est en texte brut
        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        $notifications = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(1, $notifications);

        $notification = $notifications->first();

        // ✅ Utilisation de getBody() qui retourne une string
        $this->assertStringContainsString('Bienvenue '.$this->user->name, $notification->getBody());
        $this->assertStringNotContainsString('<h1>', $notification->getBody());
        $this->assertStringNotContainsString('</h1>', $notification->getBody());
    }

    public function test_send_later_with_message_view_body_vo(): void
    {
        // Arrange : Geler le temps
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        // Arrange : Créer un body avec vue via from()
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
        ]);

        // Arrange : Créer un message
        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Delayed Welcome'),
            type: 'delayed_welcome'
        );

        // Arrange : Configurer l'envoi différé
        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: new FqcnChannelCollection,
            limit_per_channel: 1
        );

        // Act : Planifier l'envoi
        $alias = $this->service->sendLater($this->user, $message, $record);

        // Assert : Vérifier que la tâche a été créée
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        // Assert : Vérifier que le payload contient le body avec la vue rendue
        $payload = $task->getPayload();

        // ✅ Les données sont directement à la racine du payload
        $body = $payload->get('body');
        $this->assertNotNull($body);
        $this->assertStringContainsString('Bienvenue '.$this->user->name, $body);
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $body);
    }

    public function test_send_later_with_message_view_body_vo_plain_text(): void
    {
        // Arrange : Geler le temps
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        // Arrange : Créer un body en mode plainText via from()
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
            'plainText' => true,
        ]);

        // Arrange : Créer un message
        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Delayed SMS'),
            type: 'delayed_sms'
        );

        // Arrange : Configurer l'envoi différé
        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: new FqcnChannelCollection,
            limit_per_channel: 1
        );

        // Act : Planifier l'envoi
        $alias = $this->service->sendLater($this->user, $message, $record);

        // Assert : Vérifier que la tâche a été créée
        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $payload = $task->getPayload();

        // ✅ Les données sont directement à la racine du payload
        $body = $payload->get('body');
        $this->assertNotNull($body);
        $this->assertStringContainsString('Bienvenue '.$this->user->name, $body);
        $this->assertStringNotContainsString('<h1>', $body);
        $this->assertStringNotContainsString('</h1>', $body);
    }

    public function test_message_view_body_vo_throws_exception_when_view_not_found(): void
    {
        // Assert : L'exception est attendue
        $this->expectException(\InvalidArgumentException::class);
        // ✅ Le message exact de Laravel quand une vue n'existe pas
        $this->expectExceptionMessage('No hint path defined for [nonexistent].');

        // Act : Créer un body avec une vue inexistante via from()
        MessageViewBodyVO::from([
            'view' => 'nonexistent::view',
            'data' => ['name' => 'John Doe'],
        ]);
    }

    public function test_message_view_body_vo_with_empty_data(): void
    {
        // Arrange : Créer un body avec des données vides via from()
        $body = MessageViewBodyVO::from([
            'view' => 'test::simple',
            'data' => [],
        ]);

        // Act : Récupérer la valeur
        $rendered = $body->getValue();

        // Assert : Vérifier que la vue a été rendue sans erreur
        $this->assertIsString($rendered);
        $this->assertNotEmpty($rendered);
    }

    public function test_message_view_body_vo_handles_complex_data(): void
    {
        // Arrange : Créer des données complexes
        $complexData = [
            'user' => $this->user,
            'items' => [
                ['name' => 'Item 1', 'price' => 10.99],
                ['name' => 'Item 2', 'price' => 24.50],
            ],
            'total' => 35.49,
            'date' => now()->toIso8601String(),
        ];

        // Act : Créer un body avec des données complexes via from()
        $body = MessageViewBodyVO::from([
            'view' => 'test::complex',
            'data' => $complexData,
        ]);

        // Assert : Vérifier que la vue a été rendue
        $rendered = $body->getValue();
        $this->assertIsString($rendered);
        $this->assertNotEmpty($rendered);
        $this->assertStringContainsString($this->user->name, $rendered);
        $this->assertStringContainsString('Item 1', $rendered);
        $this->assertStringContainsString('35.49', $rendered);
    }

    public function test_message_view_body_vo_with_strict_associative_data(): void
    {
        // Arrange : Créer des données avec StrictAssociative
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Act : Créer un body avec StrictAssociative via from()
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => $data,
        ]);

        // Assert : Vérifier que les données sont accessibles
        $this->assertEquals('John Doe', $body->getData()->get('name'));
        $this->assertEquals('john@example.com', $body->getData()->get('email'));
        $this->assertStringContainsString('John Doe', $body->getValue());
    }

    public function test_message_view_body_vo_immutable_data_merge(): void
    {
        // Arrange : Créer un body initial via from()
        $original = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John', 'age' => 30],
        ]);

        // Act : Ajouter des données
        $modified = $original->withData(['age' => 31, 'city' => 'Paris']);

        // Assert : L'original n'a pas changé
        $this->assertEquals(30, $original->getData()->get('age'));
        $this->assertFalse($original->getData()->has('city'));

        // Assert : Le nouveau a les données fusionnées
        $this->assertEquals(31, $modified->getData()->get('age'));
        $this->assertEquals('Paris', $modified->getData()->get('city'));
        $this->assertEquals('John', $modified->getData()->get('name'));
    }
}
