<?php

namespace App\model\family;

use App\model\person\Person;
use App\model\utils\Id;

class Family {

    private Id $id;
    private FamilyName $name;
    private Person $creator;
    private array $members;

    public function __construct(Id $id, FamilyName $name, Person $creator, Person... $members) {
        $this->id = $id;
        $this->name = $name;
        $this->creator = $creator;
        $this->members = $members;
    }

}