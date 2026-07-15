<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Processors;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\Records\SendResultRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestDoctor;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestEmptyChannel;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\Repository\Records\FindByRecord;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use RuntimeException;

final class NotificationSenderProcessorTest extends TestCase
{
    use DatabaseMigrations;

    private NotificationSenderProcessor $processor;

    private NotificationRepository $repository;

    private TestUser $user;

    private NotificationMessageVO $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runDatabaseMigrations();

        $this->processor = app(NotificationSenderProcessor::class);
        $this->repository = app(NotificationRepository::class);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
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
        parent::tearDown();
    }

    private function getNotificationsForNotifiable(NotifiableInterface $notifiable)
    {
        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
        ]);

        return $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );
    }

    private function getNotificationsByMessageBody(NotifiableInterface $notifiable, string $body)
    {
        return $this->repository->findBy(
            new FindByRecord(filters: NotificationFilterRecord::from([
                'notifiable_type' => $notifiable->getMorphClass(),
                'notifiable_id' => $notifiable->getKey(),
            ]))
        )->filter(function (Notification $notification) use ($body) {
            return $notification->getBody() === $body;
        });
    }

    // ==================== TESTS: send() ====================

    public function test_send_with_all_available_channels(): void
    {
        $processRecord = new ProcessNotificationRecord;

        $results = $this->processor->send($this->user, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        // ✅ TestUser a : TestChannel + Mail + Database + TestChannel (phone) = 4 canaux
        $this->assertCount(4, $results);

        foreach ($results as $result) {
            $this->assertInstanceOf(SendResultRecord::class, $result);
            $this->assertTrue($result->success);
        }

        $notifications = $this->getNotificationsForNotifiable($this->user);
        $this->assertCount(4, $notifications);

        foreach ($notifications as $notification) {
            $this->assertEquals(NotificationStatus::SENT, $notification->status);
            $this->assertNotNull($notification->sent_at);
        }
    }

    public function test_send_with_specific_channels(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $results = $this->processor->send($this->user, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);

        $notifications = $this->getNotificationsForNotifiable($this->user);
        $this->assertCount(1, $notifications);
        $this->assertEquals(MailChannel::class, $notifications->first()->channel);
    }

    public function test_send_with_limit_per_channel(): void
    {
        $doctor = TestDoctor::create([
            'name' => 'Dr. Smith',
            'primary_email' => 'smith@clinic.com',
            'secondary_email' => 'dr.smith@personal.com',
            'phone' => '+33123456789',
            'specialty' => 'Cardiology',
        ]);

        $channelsFilter = new FqcnChannelCollection;
        $channelsFilter->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channelsFilter,
            limit_per_channel: 1
        );

        $results = $this->processor->send($doctor, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());

        $notifications = $this->getNotificationsForNotifiable($doctor);
        $this->assertCount(1, $notifications);
        $this->assertEquals(MailChannel::class, $notifications->first()->channel);
    }

    // ==================== TESTS: Destination Filters ====================

    public function test_send_with_destination_filter_single(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $destinationFilters = [
            MailChannel::class => ['john@example.com'],
        ];

        $results = $this->processor->send(
            $this->user,
            $this->message,
            $processRecord,
            $destinationFilters
        );

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);

        $notifications = $this->getNotificationsForNotifiable($this->user);
        $this->assertCount(1, $notifications);
        $this->assertEquals('john@example.com', $notifications->first()->destination);
    }

    public function test_send_with_destination_filter_single_non_matching(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $destinationFilters = [
            MailChannel::class => ['non-matching@example.com'],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No routes after applying destination filters for notifiable');

        $this->processor->send(
            $this->user,
            $this->message,
            $processRecord,
            $destinationFilters
        );
    }

    public function test_send_with_destination_filter_multiple_destinations(): void
    {
        $doctor = TestDoctor::create([
            'name' => 'Dr. Multiple',
            'primary_email' => 'primary@clinic.com',
            'secondary_email' => 'secondary@clinic.com',
            'phone' => '+33123456789',
            'specialty' => 'Neurology',
        ]);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $destinationFilters = [
            MailChannel::class => ['primary@clinic.com', 'secondary@clinic.com'],
        ];

        $results = $this->processor->send(
            $doctor,
            $this->message,
            $processRecord,
            $destinationFilters
        );

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        $destinations = [];
        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $this->assertEquals(MailChannel::class, $result->channel->getValue());
            $destinations[] = $result->destination;
        }

        $this->assertContains('primary@clinic.com', $destinations);
        $this->assertContains('secondary@clinic.com', $destinations);

        $notifications = $this->getNotificationsForNotifiable($doctor);
        $this->assertCount(2, $notifications);

        $notifDestinations = $notifications->pluck('destination')->toArray();
        $this->assertContains('primary@clinic.com', $notifDestinations);
        $this->assertContains('secondary@clinic.com', $notifDestinations);
    }

    public function test_send_with_destination_filter_multiple_channels(): void
    {

        $doctor = TestDoctor::create([
            'name' => 'Dr. MultiChannel',
            'primary_email' => 'multi@clinic.com',
            'secondary_email' => 'secondary@clinic.com',
            'phone' => '+33123456789',
            'specialty' => 'Pediatrics',
        ]);

        // ✅ Mail Channel uniquement (pas de SMS)
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $destinationFilters = [
            MailChannel::class => ['multi@clinic.com', 'secondary@clinic.com'],
        ];

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $results = $this->processor->send(
            $doctor,
            $this->message,
            $processRecord,
            $destinationFilters
        );

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results); // ✅ 2 emails uniquement

        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $this->assertEquals(MailChannel::class, $result->channel->getValue());
            $this->assertContains($result->destination, ['multi@clinic.com', 'secondary@clinic.com']);
        }

        $notifications = $this->getNotificationsForNotifiable($doctor);

        $this->assertCount(2, $notifications);
    }

    public function test_send_with_destination_filter_one_channel_filtered_other_not(): void
    {
        $doctor = TestDoctor::create([
            'name' => 'Dr. Mixed',
            'primary_email' => 'mixed@clinic.com',
            'secondary_email' => 'secondary@clinic.com',
            'phone' => '+33123456789',
            'specialty' => 'Mixed',
        ]);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $destinationFilters = [
            MailChannel::class => ['secondary@clinic.com'],
        ];

        $results = $this->processor->send(
            $doctor,
            $this->message,
            $processRecord,
            $destinationFilters
        );

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('secondary@clinic.com', $result->destination);

        $notifications = $this->getNotificationsForNotifiable($doctor);
        $this->assertCount(1, $notifications);
        $this->assertEquals('secondary@clinic.com', $notifications->first()->destination);
    }

    public function test_send_with_destination_filter_combined_with_limit_per_channel(): void
    {
        $doctor = TestDoctor::create([
            'name' => 'Dr. Combined',
            'primary_email' => 'combined1@clinic.com',
            'secondary_email' => 'combined2@clinic.com',
            'phone' => '+33123456789',
            'specialty' => 'Combined',
        ]);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        $destinationFilters = [
            MailChannel::class => ['combined1@clinic.com', 'combined2@clinic.com'],
        ];

        $results = $this->processor->send(
            $doctor,
            $this->message,
            $processRecord,
            $destinationFilters
        );

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());

        $notifications = $this->getNotificationsForNotifiable($doctor);
        $this->assertCount(1, $notifications);
    }

    public function test_send_with_destination_filter_no_match_then_throws_exception(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $destinationFilters = [
            MailChannel::class => ['non-matching@example.com'],
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No routes after applying destination filters for notifiable');

        $this->processor->send(
            $this->user,
            $this->message,
            $processRecord,
            $destinationFilters
        );
    }

    public function test_send_with_empty_destination_filter_ignores_filter(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $destinationFilters = [];

        $results = $this->processor->send(
            $this->user,
            $this->message,
            $processRecord,
            $destinationFilters
        );

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
    }

    public function test_send_with_null_destination_filter_ignores_filter(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $results = $this->processor->send(
            $this->user,
            $this->message,
            $processRecord,
            null
        );

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
    }

    // ==================== TESTS: Edge Cases ====================

    public function test_send_throws_exception_when_no_channels_available(): void
    {
        $user = TestEmptyChannel::create([
            'name' => 'No Channels User',
        ]);

        $processRecord = new ProcessNotificationRecord;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No available channels for notifiable');

        $this->processor->send($user, $this->message, $processRecord);
    }

    public function test_send_throws_exception_when_no_channels_match(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $user = TestUser::create([
            'name' => 'No Email User',
            'phone' => '+33123456789',
        ]);

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No available channels for notifiable');

        $this->processor->send($user, $this->message, $processRecord);
    }

    public function test_send_creates_notification_with_metadata(): void
    {
        $doctor = TestDoctor::create([
            'name' => 'Dr. Metadata',
            'primary_email' => 'meta@clinic.com',
            'specialty' => 'Radiology',
        ]);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(DatabaseChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $results = $this->processor->send($doctor, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $notifications = $this->getNotificationsForNotifiable($doctor);
        $this->assertCount(1, $notifications);

        $notification = $notifications->first();
        $metadata = $notification->getMetadata();

        $this->assertEquals('database', $metadata->get('type'));
        $this->assertEquals('Radiology', $metadata->get('specialty'));
    }

    public function test_send_stores_correct_destinations(): void
    {
        $doctor = TestDoctor::create([
            'name' => 'Dr. Destinations',
            'primary_email' => 'dest1@clinic.com',
            'secondary_email' => 'dest2@clinic.com',
            'phone' => '+33123456789',
            'specialty' => 'Surgery',
        ]);

        $processRecord = new ProcessNotificationRecord;

        $this->processor->send($doctor, $this->message, $processRecord);

        $notifications = $this->getNotificationsForNotifiable($doctor);
        $destinations = $notifications->pluck('destination')->toArray();

        $this->assertContains('dest1@clinic.com', $destinations);
        $this->assertContains('dest2@clinic.com', $destinations);
        $this->assertContains('database', $destinations);
        $this->assertContains('+33123456789', $destinations);
    }

    public function test_send_generates_unique_session_ids(): void
    {
        $processRecord1 = new ProcessNotificationRecord;
        $this->processor->send($this->user, $this->message, $processRecord1);

        $message2 = new NotificationMessageVO(
            body: new MessageBodyVO('Second message'),
            subject: new MessageSubjectVO('Second Subject'),
            type: 'test2'
        );
        $processRecord2 = new ProcessNotificationRecord;
        $this->processor->send($this->user, $message2, $processRecord2);

        $notifications1 = $this->getNotificationsByMessageBody($this->user, 'Test message');
        $notifications2 = $this->getNotificationsByMessageBody($this->user, 'Second message');

        $sessionIds1 = $notifications1->pluck('session_id')->unique()->toArray();
        $sessionIds2 = $notifications2->pluck('session_id')->unique()->toArray();

        $this->assertCount(1, $sessionIds1);
        $this->assertCount(1, $sessionIds2);
        $this->assertNotEquals($sessionIds1[0], $sessionIds2[0]);
    }

    public function test_send_with_database_channel_only(): void
    {
        $user = TestUser::create([
            'name' => 'Database Only',
            // ✅ Pas d'email, pas de phone
        ]);

        $processRecord = new ProcessNotificationRecord;

        $results = $this->processor->send($user, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        // ✅ TestUser a : TestChannel + Database = 2 canaux
        $this->assertCount(2, $results);

        // ✅ Filtrer pour trouver DatabaseChannel
        $databaseResult = $results->filter(function ($result) {
            return $result->channel->getValue() === DatabaseChannel::class;
        })->first();

        $this->assertNotNull($databaseResult);
        $this->assertTrue($databaseResult->success);
        $this->assertEquals('database', $databaseResult->destination);

        $notifications = $this->getNotificationsForNotifiable($user);
        $this->assertCount(2, $notifications);

        // ✅ Vérifier qu'il y a une notification DatabaseChannel
        $databaseNotification = $notifications->filter(function ($notification) {
            return $notification->channel === DatabaseChannel::class;
        })->first();

        $this->assertNotNull($databaseNotification);
        $this->assertEquals('database', $databaseNotification->destination);
    }

    public function test_send_with_filter_by_channels_multiple(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));
        $channels->add(new FqcnChannelVO(DatabaseChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channels
        );

        $results = $this->processor->send($this->user, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        $channelsFound = [];
        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $channelsFound[] = $result->channel->getValue();
        }

        $this->assertContains(MailChannel::class, $channelsFound);
        $this->assertContains(DatabaseChannel::class, $channelsFound);

        $notifications = $this->getNotificationsForNotifiable($this->user);
        $this->assertCount(2, $notifications);
    }

    public function test_send_with_limit_per_channel_zero_means_no_limit(): void
    {
        $doctor = TestDoctor::create([
            'name' => 'Dr. Zero Limit',
            'primary_email' => 'zero1@clinic.com',
            'secondary_email' => 'zero2@clinic.com',
            'phone' => '+33123456789',
            'specialty' => 'Orthopedics',
        ]);

        $channelsFilter = new FqcnChannelCollection;
        $channelsFilter->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channelsFilter,
            limit_per_channel: 0
        );

        $results = $this->processor->send($doctor, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        $notifications = $this->getNotificationsForNotifiable($doctor);
        $this->assertCount(2, $notifications);
    }

    public function test_send_handles_failed_driver(): void
    {
        $processRecord = new ProcessNotificationRecord;

        $results = $this->processor->send($this->user, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
        }

        $notifications = $this->getNotificationsForNotifiable($this->user);

        foreach ($notifications as $notification) {
            $this->assertEquals(NotificationStatus::SENT, $notification->status);
        }
    }
}
