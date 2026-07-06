<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Processors;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
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

    public function test_send_with_all_available_channels(): void
    {

        $processRecord = new ProcessNotificationRecord;

        $results = $this->processor->send($this->user, $this->message, $processRecord);

        foreach ($results as $result) {
        }

        $this->assertInstanceOf(SendResultCollection::class, $results);
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

    public function test_send_with_limit_per_channel_on_doctor_with_multiple_emails(): void
    {

        $doctor = TestDoctor::create([
            'name' => 'Dr. Smith',
            'primary_email' => 'smith@clinic.com',
            'secondary_email' => 'dr.smith@personal.com',
            'phone' => '+33123456789',
            'specialty' => 'Cardiology',
        ]);

        $channels = $doctor->getNotificationChannels();
        $this->assertCount(4, $channels);

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

    public function test_send_with_limit_per_channel_multiple_emails(): void
    {

        $doctor = TestDoctor::create([
            'name' => 'Dr. Johnson',
            'primary_email' => 'johnson@clinic.com',
            'secondary_email' => 'dr.johnson@personal.com',
            'phone' => '+33123456789',
            'specialty' => 'Neurology',
        ]);

        $channelsFilter = new FqcnChannelCollection;
        $channelsFilter->add(new FqcnChannelVO(MailChannel::class));

        $processRecord = new ProcessNotificationRecord(
            channels: $channelsFilter,
            limit_per_channel: 2
        );

        $results = $this->processor->send($doctor, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(2, $results);

        $destinations = [];
        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $this->assertEquals(MailChannel::class, $result->channel->getValue());
            $destinations[] = $result->destination;
        }

        $this->assertContains('johnson@clinic.com', $destinations);
        $this->assertContains('dr.johnson@personal.com', $destinations);

        $notifications = $this->getNotificationsForNotifiable($doctor);

        $this->assertCount(2, $notifications);

        $destinationsDb = $notifications->pluck('destination')->toArray();

        $this->assertContains('johnson@clinic.com', $destinationsDb);
        $this->assertContains('dr.johnson@personal.com', $destinationsDb);
    }

    public function test_send_with_limit_per_channel_and_all_channels(): void
    {

        $doctor = TestDoctor::create([
            'name' => 'Dr. Williams',
            'primary_email' => 'williams@clinic.com',
            'secondary_email' => 'dr.williams@personal.com',
            'phone' => '+33123456789',
            'specialty' => 'Pediatrics',
        ]);

        $processRecord = new ProcessNotificationRecord(
            limit_per_channel: 1
        );

        $results = $this->processor->send($doctor, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(3, $results);

        $channelsFound = [];
        foreach ($results as $result) {
            $this->assertTrue($result->success);
            $channelsFound[] = $result->channel->getValue();
        }

        $this->assertContains(MailChannel::class, $channelsFound);
        $this->assertContains(DatabaseChannel::class, $channelsFound);
        $this->assertContains(SmsChannel::class, $channelsFound);

        $notifications = $this->getNotificationsForNotifiable($doctor);

        $this->assertCount(3, $notifications);
    }

    public function test_send_throws_exception_when_no_channels_available(): void
    {

        $user = TestEmptyChannel::create([
            'name' => 'No Channels User',
        ]);

        $processRecord = new ProcessNotificationRecord;

        $this->expectException(\RuntimeException::class);
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

        $this->expectException(\RuntimeException::class);
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

        // ✅ Filtrer sur DatabaseChannel pour récupérer le specialty
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
        ]);

        $processRecord = new ProcessNotificationRecord;

        $results = $this->processor->send($user, $this->message, $processRecord);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(DatabaseChannel::class, $result->channel->getValue());

        $notifications = $this->getNotificationsForNotifiable($user);

        $this->assertCount(1, $notifications);
        $this->assertEquals(DatabaseChannel::class, $notifications->first()->channel);
        $this->assertEquals('database', $notifications->first()->destination);
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
