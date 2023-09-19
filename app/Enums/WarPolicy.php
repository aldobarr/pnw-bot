<?php

namespace App\Enums;

enum WarPolicy: string {
	use FromNormalized;

	CASE ARCANE = 'arcane';
	case ATTRITION = 'attrition';
	case BLITZKRIEG = 'blitzkrieg';
	case COVERT = 'covert';
	CASE FORTRESS = 'fortress';
	case GUARDIAN = 'guardian';
	case MONEYBAGS = 'moneybags';
	case PIRATE = 'pirate';
	case TACTICIAN = 'tactician';
	case TURTLE = 'turtle';

	public function getFormValue(): string {
		return ucfirst($this->value);
	}
}