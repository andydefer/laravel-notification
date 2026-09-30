<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal Eloquent model used to exercise PusherChannelNameVO::forModel()
 * inside tests, without requiring a real table or migration.
 */
final class FakeNotifiableModel extends Model
{
    protected $table = 'fake_notifiable_models';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function __construct(string $key = 'abc-123')
    {
        parent::__construct();

        $this->setAttribute($this->primaryKey, $key);
    }

    public function getMorphClass(): string
    {
        return 'fake-model';
    }

    public function getKey(): string
    {
        return (string) $this->getAttribute($this->primaryKey);
    }
}
