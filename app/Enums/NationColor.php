<?php

namespace App\Enums;

enum NationColor: string {
	use FromNormalized;

	case PURPLE = 'purple';
	case RED = 'red';
	CASE BLUE = 'blue';
	CASE PINK = 'pink';
	case WHITE = 'white';
	case BLACK = 'black';
	case ORANGE = 'orange';
	case GREEN = 'green';
	case AQUA = 'aqua';
	case YELLOW = 'yellow';
	case LIME = 'lime';
	case OLIVE = 'olive';
	case MAROON = 'maroon';
	case BROWN = 'brown';
	case BEIGE = 'beige';
	case GRAY = 'gray';

	public function getFormValue(): string {
		return '/img/colors/' . $this->value . '.png';
	}
}
