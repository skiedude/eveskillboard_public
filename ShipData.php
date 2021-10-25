<?php

namespace App\Traits;
use Cache;
use Log;

trait ShipData {

  //Create groups for ships (ie cruiser, frigate, etc)
  private function shipGroups() {
    #check SDE for all ship groups
    $groupQuery = 'SELECT groupName FROM invGroups WHERE categoryID = 6 AND groupName != "Capsule" GROUP BY groupName';
    return $groups = \DB::connection('mysql2')->select($groupQuery);
  }

  //Map out what every ship in the game requires for skills
  private function shipSkills() {

    $shipQuery = '
      SELECT
      Types.typeName AS typeName,
      Types.typeID AS typeID,
      Grouping.groupID AS typeGroupID,
      Grouping.categoryID AS typeCategoryID
        FROM
        invGroups as Grouping

        INNER JOIN invTypes AS Types
        ON Grouping.groupID = Types.groupID

        WHERE
        Types.published = 1
        AND Grouping.published = 1
        AND Grouping.categoryID = 6';

    #get every ship
    $ships = \DB::connection('mysql2')->select($shipQuery);

    $shipSkills = new \stdClass();

    //Get all Ships, and build the required skills for each ship
    foreach($ships as $ship) {
      $query = '
        SELECT
        Types.typeName AS typeName,
        Types.typeID AS typeID,
        Types.raceID AS raceID,
        Grouping.groupName AS typeGroupName,
        Grouping.groupID AS typeGroupID,
        Skills.typeName AS requiredTypeName,
        Skills.typeID as requiredTypeID,
        COALESCE(
            SkillLevel.valueFloat,
            SkillLevel.valueInt
            ) AS requiredLevel
          FROM
          dgmTypeAttributes AS SkillName

          INNER JOIN invTypes AS Types
          ON Types.typeID = SkillName.typeID
          INNER JOIN invGroups AS Grouping
          ON Grouping.groupID = Types.groupID
          INNER JOIN invTypes AS Skills
          ON (Skills.typeID = SkillName.valueInt OR Skills.typeID = SkillName.valueFloat)
          AND SkillName.attributeID IN (182, 183, 184, 1285, 1289, 1290)
          INNER JOIN dgmTypeAttributes AS SkillLevel
          ON SkillLevel.typeID = SkillName.typeID
          AND SkillLevel.attributeID IN (277, 278, 279, 1286, 1287, 1288)
          WHERE
          Types.published = 1 AND
          ((SkillName.attributeID = 182 AND
            SkillLevel.attributeID = 277) OR
           (SkillName.attributeID = 183 AND
            SkillLevel.attributeID = 278) OR
           (SkillName.attributeID = 184 AND
            SkillLevel.attributeID = 279) OR
           (SkillName.attributeID = 1285 AND
            SkillLevel.attributeID = 1286) OR
           (SkillName.attributeID = 1289 AND
            SkillLevel.attributeID = 1287) OR
           (SkillName.attributeID = 1290 AND
            SkillLevel.attributeID = 1288))
          AND Types.typeID = ?';

      $required_skills = new \stdClass();
      $loop_id = array();
      $shipID = \DB::connection('mysql2')->select($query, [$ship->typeID]);
      $counter = 0;

      #this is probably more complicated than it needed to be, and even today it takes me a minute to remember how it all works, but it somehow works
      foreach($shipID as $shID) {

        if($shID->requiredTypeID == null) {
          #skip weird ships
          continue;
        }
        #This is building a skill tree of the depending skills for every skill
        if(isset($required_skills->{$shID->requiredTypeName}) && ($shID->requiredLevel > $required_skills->{$shID->requiredTypeName}->{'requiredLevel'})) {
          $required_skills->{$shID->requiredTypeName}->{'requiredLevel'} = $shID->requiredLevel;
        } else {
          $required_skills->{$shID->requiredTypeName} = new \stdClass();
          $required_skills->{$shID->requiredTypeName}->{'skillName'} = $shID->requiredTypeName;
          $required_skills->{$shID->requiredTypeName}->{'requiredLevel'} = $shID->requiredLevel;
          $required_skills->{$shID->requiredTypeName}->{'TypeID'} = $shID->requiredTypeID;

          array_push($loop_id, $shID->requiredTypeID);
        }
        $counter++;
        $required_skills->{'info'} = new \stdClass();
        $required_skills->{'info'}->{'group'} = $shID->typeGroupName;
        $required_skills->{'info'}->{'shipTypeID'} = $shID->typeID;
        $required_skills->{'info'}->{'raceID'} = $shID->raceID;
        $required_skills->{'info'}->{'primary_skills_count'} = $counter;
      }

      # This is PHP black magic. using &$lID we are able to add new items to loop through from inside the loop, thus allowing us to recursively learn what each ship needs for skills
      # While you could just stop the loop if the character had the primary skills learned, in the case where it doesn't have the primary skills, I want to know every skill they need to train
      foreach($loop_id as &$lID) {
        $skillID = \DB::connection('mysql2')->select($query, [$lID]);

        foreach($skillID as $skID) {

          if($skID->requiredTypeID == null) {
            continue;
          }

          if(isset($required_skills->{$skID->requiredTypeName}->{'requiredLevel'}) && ($skID->requiredLevel > $required_skills->{$skID->requiredTypeName}->{'requiredLevel'})) {
            $required_skills->{$skID->requiredTypeName}->{'requiredLevel'} = $skID->requiredLevel;
          } elseif(isset($required_skills->{$skID->requiredTypeName}->{'requiredLevel'}) && ($skID->requiredLevel < $required_skills->{$skID->requiredTypeName}->{'requiredLevel'})) {
            //do nothing we want the highest required skill set
          } else {
            //create the object, we made it this far so it doesn't exist
            $required_skills->{$skID->requiredTypeName} = new \stdClass();
            $required_skills->{$skID->requiredTypeName}->{'requiredLevel'} = $skID->requiredLevel;
          }

          $required_skills->{$skID->requiredTypeName}->{'skillName'} = $skID->requiredTypeName;
          $required_skills->{$skID->requiredTypeName}->{'TypeID'} = $skID->requiredTypeID;

          if(!in_array($skID->requiredTypeID, $loop_id)){ //Don't add duplicate ID
            //PHP Magic &$ operator allows us to add items to a loop from inside the loop
            array_push($loop_id, $skID->requiredTypeID);
          }
        }
      }

      $shipSkills->{$ship->typeName} = $required_skills;
    }
    return $shipSkills;
  }

  //Used for grouping ships by Race on the Ships Fly page
  private function raceID() {
    $raceQuery = '
    SELECT
      DISTINCT Types.raceid
        FROM
        invTypes AS Types

        INNER JOIN invGroups as Grouping
        ON Grouping.groupID = Types.groupID

        WHERE
        Types.published = 1
        AND Grouping.published = 1
        AND Grouping.categoryID = 6';

    return \DB::connection('mysql2')->select($raceQuery);
  }

  private function canFly($skills, $playerAttr) {
    # Check if a character can fly a ship
    $attr_query = '
    SELECT attributeName FROM dgmTypeAttributes
    JOIN dgmAttributeTypes ON dgmAttributeTypes.attributeID = coalesce(valueFloat, valueInt)
        WHERE dgmTypeAttributes.attributeID IN (180, 181) AND dgmTypeAttributes.typeID = ?';

    $rank_query = '
    SELECT
      valueFloat AS rank 
    FROM dgmTypeAttributes 
    WHERE attributeID = 275
    AND typeID = ?';

    $canFly = new \stdClass();

    if (Cache::has('shipSkills')) {
      $shipSkills = Cache::get('shipSkills');
    } else {
      $shipSkills = $this->shipSkills();
      //These shouldn't change very often
      Cache::add('shipSkills', $shipSkills, 604800);
    }

    foreach($shipSkills as $ship => $skill) {
      $missing = array();
      $total_sec = 0;
      $counter = 0;
      $iter = 0;

      foreach($skill as $sh) {

        if(isset($sh->group) || isset($sh->shipTypeID) || isset($sh->raceID)) {
          continue;
        }

        if($counter == $skill->info->primary_skills_count && $counter <= $iter) { 
          // We have the top primary skills trained, no reason to keep going
          break;
        }
        //Because $skills now is an array of arrays instead of keys->array,
        // We need to check all the sub arrays to see if the skill exists
        $check = static::checkSkill($sh->skillName, $skills);
        if(isset($check) && $check->trained_level < $sh->requiredLevel || !isset($check)) {
          if(!isset($skills->{$sh->skillName})) {
            $rank = \DB::connection('mysql2')->select($rank_query, [$sh->TypeID]);
            $rank = $rank[0]->rank;
            $skillpoints = 0;
          } else {
            $rank = $skills->{$sh->skillName}->rank;
            $skillpoints = $skills->{$sh->skillName}->skillpoints;
          }

          $skillAttr = \DB::connection('mysql2')->select($attr_query, [$sh->TypeID]);
          //Calculate the time to train each missing skill
          $sp_level = 250 * $rank * (sqrt(32)**($sh->requiredLevel - 1)); 
          $missing_sp = $sp_level - $skillpoints;
          $sp_per_min = $playerAttr->{strtolower($skillAttr[0]->attributeName)} + ($playerAttr->{strtolower($skillAttr[1]->attributeName)} / 2);
          $train_time = ($missing_sp / $sp_per_min);
          $seconds = (round($train_time,2) * 60);
          $total_sec += $seconds;
          $hours = floor($train_time / 60);
          $days = floor($hours / 24);
          $hours = (floor($train_time / 60)) - ($days * 24);
          $seconds -= ($hours * 3600) + ($days * 86400);
          $minutes = floor($seconds /60);
          $seconds = round(($seconds - ($minutes * 60)));
          $train_string = "{$days}d {$hours}h {$minutes}m {$seconds}s";

          switch ($sh->requiredLevel) {
            case 1:
              $level = 'I';
              break;
            case 2:
              $level = 'II';
              break;
            case 3:
              $level = 'III';
              break;
            case 4:
              $level = 'IV';
              break;
            case 5:
              $level = 'V';
              break;
            default:
              break;
          }

          array_push($missing, "$sh->skillName $level ($train_string)");
        } else {
          //Count what iteration we're on
          $iter++;
        }
        // See how many skills we don't need to train
        $counter++;
      }

      if(!isset($skill->info->group)) {continue;}
      if(!isset($canFly->{$skill->info->group})) {
        $canFly->{$skill->info->group} = new \stdClass();
      }

      $canFly->{$skill->info->group}->{$ship} = new \stdClass();
      $canFly->{$skill->info->group}->{$ship}->{'raceID'} = $skill->info->raceID;
      $canFly->{$skill->info->group}->{$ship}->{'shipTypeID'} = $skill->info->shipTypeID;

      if(count($missing) > 0) {
        #build our missing skills and time to train based off rank etc
        $total_seconds = $total_sec;
        $total_hours = floor($total_sec / 3600);
        $total_days = floor($total_hours / 24);
        $total_hours = (floor($total_sec / 3600)) - ($total_days * 24);
        $total_seconds -= ($total_hours * 3600) + ($total_days * 86400);
        $total_minutes = floor($total_seconds /60);
        $total_seconds = round(($total_seconds - ($total_minutes * 60)));
        $total_train_string = "{$total_days}d {$total_hours}h {$total_minutes}m {$total_seconds}s";

        $canFly->{$skill->info->group}->{$ship}->{'missing'} = $missing;
        $canFly->{$skill->info->group}->{$ship}->{'total_time_missing'} = $total_train_string;
      }
    }
  return $canFly;
  }

  public function checkSkill($shipSkillName, $skills) {
    # helper function
    foreach($skills as $sk) {
      if($sk->skill_name == $shipSkillName && $sk->trained_level != 99) {
        $check = $sk;
        return $check;
      }
    }
    return null;
  }

  public function shipArray() {
    # used in the html ships page
    $shipQuery = '
      SELECT
      Types.typeName AS typeName,
      Types.typeID AS typeID,
      Grouping.groupID AS typeGroupID,
      Grouping.categoryID AS typeCategoryID
        FROM
        invGroups as Grouping

        INNER JOIN invTypes AS Types
        ON Grouping.groupID = Types.groupID

        WHERE
        Types.published = 1
        AND Grouping.published = 1
        AND Grouping.categoryID = 6';

    $ships = \DB::connection('mysql2')->select($shipQuery);

    $ship_names = array();
    foreach($ships as $sp) {
      array_push($ship_names, $sp->typeName);
    }
    sort($ship_names); 
    Cache::add('shipArray', $ship_names, 604800);
  }
}

