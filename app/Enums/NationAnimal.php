<?php

namespace App\Enums;

enum NationAnimal: string {
	use FromNormalized;

	case CAT = 'cat';
	case ALLIGATOR = 'alligator';
	case BALD_EAGLE = 'bald eagle';
	case BAT = 'bat';
	case BEAR = 'bear';
	case CHICKEN = 'chicken';
	case DOG = 'dog';
	case DOLPHIN = 'dolphin';
	case DUGONG = 'dugong';
	case ELEPHANT = 'elephant';
	case FOX = 'fox';
	case FROG = 'frog';
	case GECKO = 'gecko';
	case GIRAFFE = 'giraffe';
	case GOAT = 'goat';
	case GOLDEN_EAGLE = 'golden eagle';
	case GORILLA = 'gorilla';
	case KANGAROO = 'kangaroo';
	case KIWI = 'kiwi';
	case LION = 'lion';
	case OCTOPUS = 'octopus';
	case ORCA = 'orca';
	case ORYX = 'oryx';
	case PENGUIN = 'penguin';
	case PIG = 'pig';
	case PLATYPUS = 'platypus';
	case RABBIT = 'rabbit';
	case SHARK = 'shark';
	case SHEEP = 'sheep';
	case SWAN = 'swan';
	case TIGER = 'tiger';
	case TUNA = 'tuna';
	case TURKEY = 'turkey';
	case TURTLE = 'turtle';
	case WALRUS = 'walrus';
	case NONE = 'none';

	public function getFormValue(): string {
		return 'https://politicsandwar.com/img/national_animal/' . $this->value . '.png';
	}

	public static function getRandomAnimal(): static {
		$animals = static::cases();
		return $animals[random_int(0, count($animals) - 1)];
	}
}
