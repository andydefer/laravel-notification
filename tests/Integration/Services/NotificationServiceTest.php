<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Services;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Models\FcmDevice;
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
use AndyDefer\LaravelNotification\ValueObjects\PusherChannelNameVO;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Repositories\RecurringTaskRepository;
use AndyDefer\Task\Repositories\UniqueTaskRepository;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Ramsey\Uuid\Uuid;

final class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationServiceInterface $service;

    private TestUser $user;

    private NotificationMessageVO $message;

    private NotificationRepository $repository;

    private UniqueTaskRepository $uniqueTaskRepository;

    private RecurringTaskRepository $recurringTaskRepository;

    protected function setUp(): void
    {
        parent::setUp();

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
        // ✅ TestChannel + Mail + Mail secondary + Database + TestChannel phone + Pusher = 6
        $this->assertCount(6, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
        }

        $count = $this->repository->countByNotifiable($this->user);
        $this->assertEquals(6, $count);
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

        $this->assertCount(6, $results);
    }

    public function test_reset_options_manually(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class);

        $this->service->withOptions($options);
        $this->service->resetOptions();

        $results = $this->service->sendNow($this->user, $this->message);

        $this->assertCount(6, $results);
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
        $this->assertEquals(6, $stats->total);
        $this->assertEquals(6, $stats->sent);
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
        $this->assertEquals(6, $stats->total);
        $this->assertEquals(6, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->pending);
    }

    // ============================================================
    // NEW TESTS: MessageViewBodyVO
    // ============================================================

    public function test_message_view_body_vo_renders_html_view(): void
    {
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        $rendered = $body->getValue();

        $this->assertStringContainsString('<h1>Bienvenue John Doe</h1>', $rendered);
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_message_view_body_vo_renders_plain_text_view_for_sms(): void
    {
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'Jane Smith'],
            'plainText' => true,
        ]);

        $rendered = $body->getValue();

        $this->assertStringContainsString('Bienvenue Jane Smith', $rendered);
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $rendered);
        $this->assertStringNotContainsString('<h1>', $rendered);
        $this->assertStringNotContainsString('</h1>', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_message_view_body_vo_with_merge_data(): void
    {
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'mergeData' => ['signature' => 'L\'équipe Afya'],
        ]);

        $rendered = $body->getValue();

        $this->assertStringContainsString('John Doe', $rendered);
        $this->assertStringContainsString('L&#039;équipe Afya', $rendered);
    }

    public function test_message_view_body_vo_with_data_immutable(): void
    {
        $originalBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        $newBody = $originalBody->withData(['extra' => 'value']);

        $this->assertNotSame($originalBody, $newBody);
        $this->assertEquals('John Doe', $originalBody->getData()->get('name'));
        $this->assertFalse($originalBody->getData()->has('extra'));
        $this->assertEquals('John Doe', $newBody->getData()->get('name'));
        $this->assertEquals('value', $newBody->getData()->get('extra'));
    }

    public function test_message_view_body_vo_can_be_used_as_message_body(): void
    {
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        $this->assertInstanceOf(MessageBodyVO::class, $message->body);
        $this->assertInstanceOf(MessageViewBodyVO::class, $message->body);
        $this->assertStringContainsString('Bienvenue John Doe', $message->body->getValue());
    }

    public function test_message_view_body_vo_plain_text_renders_for_sms_channel(): void
    {
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'plainText' => true,
        ]);

        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('SMS Notification'),
            type: 'sms'
        );

        $this->assertTrue($viewBody->isPlainText());
        $this->assertStringContainsString('Bienvenue John Doe', $message->body->getValue());
        $this->assertStringNotContainsString('<h1>', $message->body->getValue());
    }

    public function test_message_view_body_vo_html_helper(): void
    {
        $body = MessageViewBodyVO::html(
            view: 'test::welcome',
            data: ['name' => 'John Doe']
        );

        $this->assertFalse($body->isPlainText());
        $this->assertStringContainsString('<h1>', $body->getValue());
    }

    public function test_message_view_body_vo_plain_helper(): void
    {
        $body = MessageViewBodyVO::plain(
            view: 'test::welcome',
            data: ['name' => 'John Doe']
        );

        $this->assertTrue($body->isPlainText());
        $this->assertStringContainsString('Bienvenue John Doe', $body->getValue());
        $this->assertStringNotContainsString('<h1>', $body->getValue());
    }

    public function test_message_view_body_vo_as_html_conversion(): void
    {
        $plainBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'plainText' => true,
        ]);

        $htmlBody = $plainBody->asHtml();

        $this->assertTrue($plainBody->isPlainText());
        $this->assertFalse($htmlBody->isPlainText());
        $this->assertNotSame($plainBody, $htmlBody);
    }

    public function test_message_view_body_vo_as_plain_text_conversion(): void
    {
        $htmlBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ]);

        $plainBody = $htmlBody->asPlainText();

        $this->assertFalse($htmlBody->isPlainText());
        $this->assertTrue($plainBody->isPlainText());
        $this->assertNotSame($htmlBody, $plainBody);
    }

    public function test_message_view_body_vo_chained_methods(): void
    {
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
        ])
            ->withData(['extra' => 'value'])
            ->asPlainText()
            ->withMergeData(['signature' => 'Team']);

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
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'plainText' => false,
        ]);

        $this->assertInstanceOf(MessageViewBodyVO::class, $body);
        $this->assertEquals('test::welcome', $body->getView());
        $this->assertEquals('John Doe', $body->getData()->get('name'));
        $this->assertFalse($body->isPlainText());
        $this->assertStringContainsString('Bienvenue John Doe', $body->getValue());
    }

    public function test_message_view_body_vo_from_array_with_merge_data(): void
    {
        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John Doe'],
            'mergeData' => ['signature' => 'L\'équipe Afya'],
            'plainText' => true,
        ]);

        $this->assertTrue($body->isPlainText());
        $this->assertEquals('John Doe', $body->getData()->get('name'));
        $this->assertEquals('L\'équipe Afya', $body->getMergeData()->get('signature'));
        $this->assertStringContainsString('John Doe', $body->getValue());
        $this->assertStringContainsString('L\'équipe Afya', $body->getValue());
        $this->assertStringNotContainsString('<h1>', $body->getValue());
    }

    public function test_send_now_with_message_view_body_vo(): void
    {
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
        ]);

        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Welcome Email'),
            type: 'welcome'
        );

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        $results = $this->service->sendNow($this->user, $message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $this->assertEquals(MailChannel::class, $result->channel->getValue());
        }

        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        $notifications = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(1, $notifications);

        $notification = $notifications->first();

        $this->assertStringContainsString('Bienvenue '.$this->user->name, $notification->getBody());
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $notification->getBody());
    }

    public function test_send_now_with_message_view_body_vo_plain_text(): void
    {
        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
            'plainText' => true,
        ]);

        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('SMS Welcome'),
            type: 'sms_welcome'
        );

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(TestChannel::class));

        $record = new SendNowRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        $results = $this->service->sendNow($this->user, $message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
        }

        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        $notifications = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(1, $notifications);

        $notification = $notifications->first();

        $this->assertStringContainsString('Bienvenue '.$this->user->name, $notification->getBody());
        $this->assertStringNotContainsString('<h1>', $notification->getBody());
        $this->assertStringNotContainsString('</h1>', $notification->getBody());
    }

    public function test_send_later_with_message_view_body_vo(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
        ]);

        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Delayed Welcome'),
            type: 'delayed_welcome'
        );

        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: new FqcnChannelCollection,
            limit_per_channel: 1
        );

        $alias = $this->service->sendLater($this->user, $message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();

        $body = $payload->get('body');
        $this->assertNotNull($body);
        $this->assertStringContainsString('Bienvenue '.$this->user->name, $body);
        $this->assertStringContainsString('Nous sommes ravis de vous accueillir', $body);
    }

    public function test_send_later_with_message_view_body_vo_plain_text(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $viewBody = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => $this->user->name],
            'plainText' => true,
        ]);

        $message = new NotificationMessageVO(
            body: $viewBody,
            subject: new MessageSubjectVO('Delayed SMS'),
            type: 'delayed_sms'
        );

        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: new FqcnChannelCollection,
            limit_per_channel: 1
        );

        $alias = $this->service->sendLater($this->user, $message, $record);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $payload = $task->getPayload();

        $body = $payload->get('body');
        $this->assertNotNull($body);
        $this->assertStringContainsString('Bienvenue '.$this->user->name, $body);
        $this->assertStringNotContainsString('<h1>', $body);
        $this->assertStringNotContainsString('</h1>', $body);
    }

    public function test_message_view_body_vo_throws_exception_when_view_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No hint path defined for [nonexistent].');

        MessageViewBodyVO::from([
            'view' => 'nonexistent::view',
            'data' => ['name' => 'John Doe'],
        ]);
    }

    public function test_message_view_body_vo_with_empty_data(): void
    {
        $body = MessageViewBodyVO::from([
            'view' => 'test::simple',
            'data' => [],
        ]);

        $rendered = $body->getValue();

        $this->assertIsString($rendered);
        $this->assertNotEmpty($rendered);
    }

    public function test_message_view_body_vo_handles_complex_data(): void
    {
        $complexData = [
            'user' => $this->user,
            'items' => [
                ['name' => 'Item 1', 'price' => 10.99],
                ['name' => 'Item 2', 'price' => 24.50],
            ],
            'total' => 35.49,
            'date' => now()->toIso8601String(),
        ];

        $body = MessageViewBodyVO::from([
            'view' => 'test::complex',
            'data' => $complexData,
        ]);

        $rendered = $body->getValue();
        $this->assertIsString($rendered);
        $this->assertNotEmpty($rendered);
        $this->assertStringContainsString($this->user->name, $rendered);
        $this->assertStringContainsString('Item 1', $rendered);
        $this->assertStringContainsString('35.49', $rendered);
    }

    public function test_message_view_body_vo_with_strict_associative_data(): void
    {
        $data = StrictAssociative::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $body = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => $data,
        ]);

        $this->assertEquals('John Doe', $body->getData()->get('name'));
        $this->assertEquals('john@example.com', $body->getData()->get('email'));
        $this->assertStringContainsString('John Doe', $body->getValue());
    }

    public function test_message_view_body_vo_immutable_data_merge(): void
    {
        $original = MessageViewBodyVO::from([
            'view' => 'test::welcome',
            'data' => ['name' => 'John', 'age' => 30],
        ]);

        $modified = $original->withData(['age' => 31, 'city' => 'Paris']);

        $this->assertEquals(30, $original->getData()->get('age'));
        $this->assertFalse($original->getData()->has('city'));

        $this->assertEquals(31, $modified->getData()->get('age'));
        $this->assertEquals('Paris', $modified->getData()->get('city'));
        $this->assertEquals('John', $modified->getData()->get('name'));
    }

    // ============================================================
    // NEW TESTS: FCM Devices
    // ============================================================

    public function test_send_now_with_fcm_device(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        FcmDevice::create([
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now(),
        ]);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(FirebaseCloudMessagingChannel::class));

        $record = new SendNowRecord(channels: $channels);

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success, sprintf(
            'FCM send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertEquals(FirebaseCloudMessagingChannel::class, $result->channel->getValue());
        $this->assertEquals($token, $result->destination);
    }

    public function test_send_now_with_fcm_device_and_pusher_together(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        FcmDevice::create([
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now(),
        ]);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(FirebaseCloudMessagingChannel::class));
        $channels->add(new FqcnChannelVO(PusherChannel::class));

        $record = new SendNowRecord(channels: $channels);

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        $channelsFound = $results->map(fn ($r) => $r->channel->getValue())->toArray();
        $this->assertContains(FirebaseCloudMessagingChannel::class, $channelsFound);
        $this->assertContains(PusherChannel::class, $channelsFound);
    }

    public function test_send_now_with_fcm_options_limit_per_channel(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        FcmDevice::create([
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now(),
        ]);

        $options = SendOptions::init()
            ->withChannel(FirebaseCloudMessagingChannel::class)
            ->withLimitPerChannel(1);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals(FirebaseCloudMessagingChannel::class, $results->first()->channel->getValue());
    }

    public function test_send_now_with_fcm_options_destination_filter(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        FcmDevice::create([
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now(),
        ]);

        $options = SendOptions::init()
            ->withChannel(FirebaseCloudMessagingChannel::class)
            ->withDestinationFilter(FirebaseCloudMessagingChannel::class, $token);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertCount(1, $results);
        $this->assertEquals($token, $results->first()->destination);
    }

    public function test_send_later_with_fcm_device(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        FcmDevice::create([
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now(),
        ]);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(FirebaseCloudMessagingChannel::class));

        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: $channels,
        );

        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(FirebaseCloudMessagingChannel::class, $payload->get('channels'));
    }

    // ============================================================
    // NEW TESTS: Pusher
    // ============================================================

    public function test_send_now_with_pusher_channel(): void
    {
        $channel = PusherChannelNameVO::forModel($this->user)->getValue();

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(PusherChannel::class));

        $record = new SendNowRecord(channels: $channels);

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success, sprintf(
            'Pusher send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertEquals(PusherChannel::class, $result->channel->getValue());
        $this->assertEquals($channel, $result->destination);
    }

    public function test_send_now_with_pusher_options_limit_per_channel(): void
    {
        $options = SendOptions::init()
            ->withChannel(PusherChannel::class)
            ->withLimitPerChannel(1);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals(PusherChannel::class, $results->first()->channel->getValue());
    }

    public function test_send_now_with_pusher_destination_filter(): void
    {
        $channel = PusherChannelNameVO::forModel($this->user)->getValue();

        $options = SendOptions::init()
            ->withChannel(PusherChannel::class)
            ->withDestinationFilter(PusherChannel::class, $channel);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($this->user, $this->message);

        $this->assertCount(1, $results);
        $this->assertEquals($channel, $results->first()->destination);
    }

    public function test_send_later_with_pusher_channel(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(PusherChannel::class));

        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: $channels,
        );

        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(PusherChannel::class, $payload->get('channels'));
    }

    public function test_send_recurring_with_pusher_channel(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(PusherChannel::class));

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            channels: $channels,
        );

        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(PusherChannel::class, $payload->get('channels'));
    }

    public function test_send_now_with_all_channels_including_fcm_and_pusher(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        FcmDevice::create([
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now(),
        ]);

        $record = new SendNowRecord;

        $results = $this->service->sendNow($this->user, $this->message, $record);

        // ✅ TestChannel + Mail + Mail secondary + Database + TestChannel (phone) + Pusher + FCM = 7
        $this->assertCount(7, $results);

        $channels = $results->map(fn ($r) => $r->channel->getValue())->toArray();
        $this->assertContains(FirebaseCloudMessagingChannel::class, $channels);
        $this->assertContains(PusherChannel::class, $channels);
    }
}
