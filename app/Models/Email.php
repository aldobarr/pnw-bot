<?php

namespace App\Models;

use App\Enums\MailHandler;
use App\Mail\Contracts\Mail;
use App\Mail\MailFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Email extends Model {
	use HasFactory;

	/**
	 * The attributes that should be cast.
	 *
	 * @var array<string, string>
	 */
	protected $casts = [
		'type' => MailHandler::class,
	];

	/**
	 * The "booted" method of the model.
	 *
	 * @return void
	 */
	protected static function booted(): void {
		static::saving(function(self $model) {
			if (empty($model->attributes['additional_data'])) {
				$model->forceFill(['additional_data' => new \stdClass]);
			}
		});
	}

	public function getMailHandler(): Mail {
		return MailFactory::create($this->type, $this->login, $this->password);
	}

	protected function password(): Attribute {
		return Attribute::make(
			get: fn(string $value) => decrypt($value),
			set: fn(string $value) => encrypt($value)
		);
	}

	protected function additionalData(): Attribute {
		return Attribute::make(
			get: fn($value) => decrypt($value),
			set: fn($value) => encrypt($value)
		);
	}

	public function account(): HasOne {
		return $this->hasOne(BotAccount::class);
	}

	public static function areThereAnyUnusedEmails(bool $use_tempmail): bool {
		return static::doesntHave('account')->where('type', $use_tempmail ? '=' : '!=', MailHandler::SECMAIL->name)->exists();
	}

	public static function getUnusedEmail(bool $use_tempmail): ?static {
		return static::doesntHave('account')
					->where('type', $use_tempmail ? '=' : '!=', MailHandler::SECMAIL->name)
					->inRandomOrder()->first();
	}
}
