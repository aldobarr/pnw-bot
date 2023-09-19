<?php

namespace App\Enums;

trait FromNormalized {
	public static function fromNormalized(string $value): static {
		return static::from(strtolower($value));
	}
}
