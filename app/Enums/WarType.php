<?php

namespace App\Enums;

enum WarType: string {
	case ORDINARY = 'ORDINARY';
	case ATTRITION = 'ATTRITION';
	case RAID = 'RAID';

	public function getFormValue(): string {
		return match($this) {
			static::ORDINARY => 'ord',
			static::ATTRITION => 'att',
			static::RAID => 'raid',
			default => ''
		};
	}
}
