<?php

namespace App\application\person;

use App\model\person\Identity;
use App\model\person\Person;

interface PersonDAO {

	public function getAllPeople(): array;

	public function getPerson(Identity $identity): ?Person;

}