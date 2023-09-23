<?php

namespace App\Models;

use App\Casts\Serialize;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model {
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'event_logs';

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'payload' => Serialize::class,
		'exception' => Serialize::class,
	];

	public function account(): BelongsTo {
		return $this->belongsTo(BotAccount::class);
	}

	public static function logEvent(string $name, ?BotAccount $account, mixed $payload = null, mixed $secondary_payload = null, ?\Throwable $exception = null): static {
		$event = new static;
		$event->name = $name;
		$event->account_id = $account?->id;
		$event->payload = $payload;
		$event->secondary_payload = $secondary_payload;
		$event->exception = $exception;
		$event->save();

		return $event;
	}
}
