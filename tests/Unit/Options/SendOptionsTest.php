<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Unit\Options;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SlackChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use PHPUnit\Framework\TestCase;

final class SendOptionsTest extends TestCase
{
    public function test_init_returns_new_instance(): void
    {
        $options = SendOptions::init();

        $this->assertInstanceOf(SendOptions::class, $options);
        $this->assertNull($options->channels);
        $this->assertNull($options->limitPerChannel);
        $this->assertNull($options->destinationFilters);
    }

    public function test_init_fluent_chaining(): void
    {
        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withLimitPerChannel(5);

        $this->assertNotNull($options->channels);
        $this->assertCount(1, $options->channels);
        $this->assertSame(5, $options->limitPerChannel);
    }

    public function test_constructor_with_default_values(): void
    {
        $options = new SendOptions;

        $this->assertNull($options->channels);
        $this->assertNull($options->limitPerChannel);
        $this->assertNull($options->destinationFilters);
    }

    public function test_constructor_with_custom_values(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $filters = new StrictAssociative([
            MailChannel::class => ['user@example.com'],
        ]);

        $options = new SendOptions(
            channels: $channels,
            limitPerChannel: 2,
            destinationFilters: $filters,
        );

        $this->assertSame($channels, $options->channels);
        $this->assertSame(2, $options->limitPerChannel);
        $this->assertSame($filters, $options->destinationFilters);
    }

    public function test_with_channel_creates_new_instance_with_single_channel(): void
    {
        $options = SendOptions::init();
        $newOptions = $options->withChannel(MailChannel::class);

        $this->assertNotSame($options, $newOptions);
        $this->assertNull($options->channels);

        $this->assertNotNull($newOptions->channels);
        $this->assertCount(1, $newOptions->channels);
        $this->assertTrue($newOptions->channels->hasChannel(MailChannel::class));
    }

    public function test_with_channel_preserves_existing_channels(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(SmsChannel::class));

        $options = new SendOptions(channels: $channels);
        $newOptions = $options->withChannel(MailChannel::class);

        $this->assertNotNull($newOptions->channels);
        $this->assertCount(2, $newOptions->channels);
        $this->assertTrue($newOptions->channels->hasChannel(SmsChannel::class));
        $this->assertTrue($newOptions->channels->hasChannel(MailChannel::class));
    }

    public function test_with_channel_preserves_other_properties(): void
    {
        $filters = new StrictAssociative([
            MailChannel::class => ['user@example.com'],
        ]);

        $options = new SendOptions(
            limitPerChannel: 3,
            destinationFilters: $filters,
        );

        $newOptions = $options->withChannel(MailChannel::class);

        $this->assertSame(3, $newOptions->limitPerChannel);
        $this->assertSame($filters, $newOptions->destinationFilters);
        $this->assertNotNull($newOptions->channels);
        $this->assertCount(1, $newOptions->channels);
    }

    public function test_with_channels_creates_new_instance_with_multiple_channels(): void
    {
        $options = SendOptions::init();
        $newOptions = $options->withChannels([MailChannel::class, SmsChannel::class]);

        $this->assertNotSame($options, $newOptions);
        $this->assertNull($options->channels);

        $this->assertNotNull($newOptions->channels);
        $this->assertCount(2, $newOptions->channels);
        $this->assertTrue($newOptions->channels->hasChannel(MailChannel::class));
        $this->assertTrue($newOptions->channels->hasChannel(SmsChannel::class));
    }

    public function test_with_channels_preserves_existing_channels(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(SlackChannel::class));

        $options = new SendOptions(channels: $channels);
        $newOptions = $options->withChannels([MailChannel::class, SmsChannel::class]);

        $this->assertNotNull($newOptions->channels);
        $this->assertCount(3, $newOptions->channels);
        $this->assertTrue($newOptions->channels->hasChannel(SlackChannel::class));
        $this->assertTrue($newOptions->channels->hasChannel(MailChannel::class));
        $this->assertTrue($newOptions->channels->hasChannel(SmsChannel::class));
    }

    public function test_with_channels_preserves_other_properties(): void
    {
        $filters = new StrictAssociative([
            MailChannel::class => ['user@example.com'],
        ]);

        $options = new SendOptions(
            limitPerChannel: 5,
            destinationFilters: $filters,
        );

        $newOptions = $options->withChannels([MailChannel::class]);

        $this->assertSame(5, $newOptions->limitPerChannel);
        $this->assertSame($filters, $newOptions->destinationFilters);
    }

    public function test_with_limit_per_channel_creates_new_instance_with_limit(): void
    {
        $options = SendOptions::init();
        $newOptions = $options->withLimitPerChannel(10);

        $this->assertNotSame($options, $newOptions);
        $this->assertNull($options->limitPerChannel);
        $this->assertSame(10, $newOptions->limitPerChannel);
    }

    public function test_with_limit_per_channel_preserves_other_properties(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $filters = new StrictAssociative([
            MailChannel::class => ['user@example.com'],
        ]);

        $options = new SendOptions(
            channels: $channels,
            destinationFilters: $filters,
        );

        $newOptions = $options->withLimitPerChannel(7);

        $this->assertSame($channels, $newOptions->channels);
        $this->assertSame($filters, $newOptions->destinationFilters);
        $this->assertSame(7, $newOptions->limitPerChannel);
    }

    public function test_with_destination_filter_adds_single_destination_for_channel(): void
    {
        $options = SendOptions::init();
        $newOptions = $options->withDestinationFilter(MailChannel::class, 'user@example.com');

        $this->assertNotSame($options, $newOptions);
        $this->assertNull($options->destinationFilters);

        $this->assertNotNull($newOptions->destinationFilters);
        $filters = $newOptions->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filters);
        $this->assertSame(['user@example.com'], $filters[MailChannel::class]);
    }

    public function test_with_destination_filter_adds_multiple_destinations_for_channel(): void
    {
        $options = SendOptions::init();
        $newOptions = $options->withDestinationFilter(
            MailChannel::class,
            ['user@example.com', 'admin@example.com']
        );

        $this->assertNotNull($newOptions->destinationFilters);
        $filters = $newOptions->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filters);
        $this->assertSame(['user@example.com', 'admin@example.com'], $filters[MailChannel::class]);
    }

    public function test_with_destination_filter_appends_to_existing_filters(): void
    {
        $existingFilters = new StrictAssociative([
            MailChannel::class => ['existing@example.com'],
            SmsChannel::class => ['+1234567890'],
        ]);

        $options = new SendOptions(destinationFilters: $existingFilters);
        $newOptions = $options->withDestinationFilter(MailChannel::class, 'new@example.com');

        $this->assertNotNull($newOptions->destinationFilters);
        $filters = $newOptions->destinationFilters->toArray();

        $this->assertArrayHasKey(MailChannel::class, $filters);
        $this->assertContains('existing@example.com', $filters[MailChannel::class]);
        $this->assertContains('new@example.com', $filters[MailChannel::class]);

        $this->assertArrayHasKey(SmsChannel::class, $filters);
        $this->assertSame(['+1234567890'], $filters[SmsChannel::class]);
    }

    public function test_with_destination_filter_appends_multiple_destinations(): void
    {
        $existingFilters = new StrictAssociative([
            MailChannel::class => ['existing@example.com'],
        ]);

        $options = new SendOptions(destinationFilters: $existingFilters);
        $newOptions = $options->withDestinationFilter(
            MailChannel::class,
            ['new1@example.com', 'new2@example.com']
        );

        $filters = $newOptions->destinationFilters->toArray();
        $this->assertCount(3, $filters[MailChannel::class]);
        $this->assertContains('existing@example.com', $filters[MailChannel::class]);
        $this->assertContains('new1@example.com', $filters[MailChannel::class]);
        $this->assertContains('new2@example.com', $filters[MailChannel::class]);
    }

    public function test_with_destination_filter_preserves_other_properties(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $options = new SendOptions(
            channels: $channels,
            limitPerChannel: 3,
        );

        $newOptions = $options->withDestinationFilter(MailChannel::class, 'user@example.com');

        $this->assertSame($channels, $newOptions->channels);
        $this->assertSame(3, $newOptions->limitPerChannel);
        $this->assertNotNull($newOptions->destinationFilters);
    }

    public function test_with_destination_filters_replaces_all_filters(): void
    {
        $existingFilters = new StrictAssociative([
            MailChannel::class => ['old@example.com'],
        ]);

        $options = new SendOptions(destinationFilters: $existingFilters);
        $newOptions = $options->withDestinationFilters([
            SmsChannel::class => ['+1234567890'],
            SlackChannel::class => ['#general'],
        ]);

        $this->assertNotSame($options, $newOptions);

        $filters = $newOptions->destinationFilters->toArray();
        $this->assertArrayNotHasKey(MailChannel::class, $filters);
        $this->assertArrayHasKey(SmsChannel::class, $filters);
        $this->assertArrayHasKey(SlackChannel::class, $filters);
        $this->assertSame(['+1234567890'], $filters[SmsChannel::class]);
        $this->assertSame(['#general'], $filters[SlackChannel::class]);
    }

    public function test_with_destination_filters_preserves_other_properties(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $options = new SendOptions(
            channels: $channels,
            limitPerChannel: 5,
        );

        $newOptions = $options->withDestinationFilters([
            SmsChannel::class => ['+1234567890'],
        ]);

        $this->assertSame($channels, $newOptions->channels);
        $this->assertSame(5, $newOptions->limitPerChannel);
        $this->assertNotNull($newOptions->destinationFilters);
    }

    public function test_get_destination_filters_returns_filters(): void
    {
        $filters = new StrictAssociative([
            MailChannel::class => ['user@example.com'],
        ]);

        $options = new SendOptions(destinationFilters: $filters);

        $this->assertSame($filters, $options->getDestinationFilters());
    }

    public function test_get_destination_filters_returns_null_when_no_filters(): void
    {
        $options = SendOptions::init();

        $this->assertNull($options->getDestinationFilters());
    }

    public function test_fluent_method_chaining_with_init(): void
    {
        $result = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withChannel(SmsChannel::class)
            ->withLimitPerChannel(3)
            ->withDestinationFilter(MailChannel::class, 'user@example.com')
            ->withDestinationFilter(SmsChannel::class, '+1234567890');

        $this->assertNotNull($result->channels);
        $this->assertCount(2, $result->channels);
        $this->assertTrue($result->channels->hasChannel(MailChannel::class));
        $this->assertTrue($result->channels->hasChannel(SmsChannel::class));
        $this->assertSame(3, $result->limitPerChannel);

        $filters = $result->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filters);
        $this->assertContains('user@example.com', $filters[MailChannel::class]);
        $this->assertArrayHasKey(SmsChannel::class, $filters);
        $this->assertContains('+1234567890', $filters[SmsChannel::class]);
    }

    public function test_fluent_method_chaining_with_new_and_parens(): void
    {
        $result = (new SendOptions)
            ->withChannel(MailChannel::class)
            ->withChannel(SmsChannel::class)
            ->withLimitPerChannel(3)
            ->withDestinationFilter(MailChannel::class, 'user@example.com')
            ->withDestinationFilter(SmsChannel::class, '+1234567890');

        $this->assertNotNull($result->channels);
        $this->assertCount(2, $result->channels);
        $this->assertTrue($result->channels->hasChannel(MailChannel::class));
        $this->assertTrue($result->channels->hasChannel(SmsChannel::class));
        $this->assertSame(3, $result->limitPerChannel);

        $filters = $result->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filters);
        $this->assertContains('user@example.com', $filters[MailChannel::class]);
        $this->assertArrayHasKey(SmsChannel::class, $filters);
        $this->assertContains('+1234567890', $filters[SmsChannel::class]);
    }

    public function test_immutability(): void
    {
        $original = SendOptions::init();
        $modified = $original->withChannel(MailChannel::class);

        $this->assertNull($original->channels);
        $this->assertNotNull($modified->channels);
        $this->assertCount(1, $modified->channels);
        $this->assertTrue($modified->channels->hasChannel(MailChannel::class));
        $this->assertNotSame($original, $modified);
    }

    public function test_immutability_with_multiple_modifications(): void
    {
        $original = SendOptions::init();

        $step1 = $original->withChannel(MailChannel::class);
        $step2 = $step1->withLimitPerChannel(5);
        $step3 = $step2->withDestinationFilter(MailChannel::class, 'user@example.com');

        $this->assertNull($original->channels);
        $this->assertNull($original->limitPerChannel);
        $this->assertNull($original->destinationFilters);

        $this->assertNotNull($step1->channels);
        $this->assertNull($step1->limitPerChannel);
        $this->assertNull($step1->destinationFilters);

        $this->assertNotNull($step2->channels);
        $this->assertSame(5, $step2->limitPerChannel);
        $this->assertNull($step2->destinationFilters);

        $this->assertNotNull($step3->channels);
        $this->assertSame(5, $step3->limitPerChannel);
        $this->assertNotNull($step3->destinationFilters);

        $this->assertNotSame($original, $step1);
        $this->assertNotSame($step1, $step2);
        $this->assertNotSame($step2, $step3);
    }

    public function test_strict_associative_preserves_casing(): void
    {
        $options = SendOptions::init()
            ->withDestinationFilter(MailChannel::class, 'user@example.com');

        $filters = $options->destinationFilters->toArray();
        $this->assertArrayHasKey(MailChannel::class, $filters);
        $this->assertSame(['user@example.com'], $filters[MailChannel::class]);

        // ✅ La casse est préservée (StrictAssociative ne normalise pas en camelCase)
        $this->assertArrayNotHasKey('mailChannel', $filters);
    }
}
