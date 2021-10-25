<?php

namespace App\Traits;

use Spatie\ArrayToXml\ArrayToXml;
use App\Traits\FormatName;
use App\Traits\ShipData;
use Cache;
use Carbon\Carbon;
use Log;
use App\Typeid;
use App\Faction;
use App\NpcCorp;
use App\Character;
use GuzzleHttp\Promise;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;

trait Skills {
  use FormatName;
  use ShipData;

  # Build a giant array of all known skills and unknown skills of the character
  public function mapSkills($skills) {
    $skillz = array();
    $skilla = array();
    $known_skills = array();

    
    foreach($skills as $sk) {
      #TypeID table was a map of every character that contained the related data (max_sp, rank, type_id), see the lookupNewSkill function on how this is created
      #Lookup data on all the skills the character knows
      $entry = Typeid::where('type_id', $sk->skill_id)->first();
      if(!is_null($entry)) {
        $array = array(
            'skill_name' => $entry->skill_name,
            'skillpoints' => number_format($sk->skillpoints_in_skill),
            'trained_level' => $sk->trained_skill_level,
            'skill_max_sp' => $entry->maxsp,
            'group_name' => $entry->group_name,
            'rank' => $entry->rank,
            'type_id' => $entry->type_id
            );
        array_push($known_skills, $entry->type_id);
        array_push($skillz, (object)$array);
      }
    }
    #known test skills to avoid, more maybe needed in the future
    $avoid_type_id =  [32856,3755,3758,9955,10264,11075,11078,12097,12099,19430,3364,3365,3366,3367,3369,3370,3371,3372,3381,3382,3383,3384,3391,11858,11208,12368,28604,28631,22172,23599,3445,3448,11015,12834,13069,13070,13071,13072,13073,13074,13075,28261,3362,11206,11204,3390,28373,30536,30541,30542,30543, 2270, 2167, 2168, 2169, 2170];
    
    $all_skills = Typeid::whereIntegerNotInRaw('type_id', $known_skills)->get();

    foreach($all_skills as $ak) {
      #Lookup the data on all the skills that the character doesn't know
      if(!in_array($ak->type_id, $avoid_type_id)){
        $array = array(
            'skill_name' => $ak->skill_name,
            'skillpoints' => 0,
            'trained_level' => 99,
            'skill_max_sp' => $ak->maxsp,
            'group_name' => $ak->group_name,
            'rank' => $ak->rank,
            'type_id' => $ak->type_id
           );
        array_push($skilla, (object)$array);
      } 
    }
    $combined = array_merge($skillz,$skilla);
    //Sort alphabetically
    usort($combined, function($a, $b) {
      return strcmp($a->skill_name, $b->skill_name);
    });

    return $combined;
  }

  public function lookupNewSkill() {
    # This was run as a scheduled job using Laravels job framework to lookup new skills daily
    # It builds a database of all skills with their rank, max_sp, group_name, typid, name
    $ranktier = new \stdClass();
    $ranktier->{'1'} = "256,000";
    $ranktier->{'2'} = "512,000";
    $ranktier->{'3'} = "768,000";
    $ranktier->{'4'} = "1,024,000";
    $ranktier->{'5'} = "1,280,000";
    $ranktier->{'6'} = "1,536,000";
    $ranktier->{'7'} = "1,782,000";
    $ranktier->{'8'} = "2,048,000";
    $ranktier->{'9'} = "2,304,000";
    $ranktier->{'10'} = "2,560,000";
    $ranktier->{'11'} = "2,816,000";
    $ranktier->{'12'} = "3,072,000";
    $ranktier->{'13'} = "3,328,000";
    $ranktier->{'14'} = "3,584,000";
    $ranktier->{'15'} = "3,840,000";
    $ranktier->{'16'} = "4,096,000";

    $noauth_headers = [
      'headers' => [
        'User-Agent' => env('USERAGENT'),
      ],
    ];

    $handler_stack = HandlerStack::create();
    $handler_stack->push(\GuzzleHttp\Middleware::retry(function($retry, $request, $value, $reason) {
      // If we have a value already, we should be able to proceed quickly.
      $sc = $value->getStatusCode();
      if ($value !== NULL && $sc != 502) {
        return FALSE;
      }
      if($sc == 502) {
        Log::debug('Retrying 502');
      }
      // Reject after 1 additional retries.
      return $retry < 1;
      }));


    $client = new Client(['base_uri' => env('CCP_URL'), 'handler' => $handler_stack]);

    $groupsEndpoint = "https://esi.evetech.net/latest/universe/categories/16";
    $groupsRes = $client->get($groupsEndpoint, $noauth_headers);
    $groups = json_decode($groupsRes->getBody())->groups;
    foreach ($groups as $group) {
      $grpEndpoint = "https://esi.evetech.net/latest/universe/groups/$group";
      $groupRes = $client->get($grpEndpoint, $noauth_headers);
      $groupResp = json_decode($groupRes->getBody());

      foreach ($groupResp->types as $id) {
        $typesEndpoint = "https://esi.evetech.net/latest/universe/types/$id";
        $typesRes = $client->get($typesEndpoint, $noauth_headers);
        $typesResp = json_decode($typesRes->getBody());
        foreach($typesResp->dogma_attributes as $att) {
          if($att->attribute_id == 275) {
            $rank = $att->value;
          }
        }
          if($typesResp->name == 'Polaris' || $typesResp->name == 'Omnipotent' || $typesResp->name == 'Test') {
            continue;
          }

        $max = $ranktier->{$rank}; 
        Typeid::updateOrCreate(
          ['type_id' => $id],
          ['group_name' => $groupResp->name,
           'maxsp' => $max,
           'rank' => $rank,
           'type_id' => $id,
           'skill_name' => $typesResp->name
          ]
        );
      }
    }

  }
  
  public function mapSkillGroups() {
    # Used to create an array of all the skill group names for displaying
    return Typeid::select('group_name')->groupBy('group_name')->get();;
  }

  public function mapXML($character, $security_status, $skillMap, $attributes) {
    # Create the XML dump of a character that would import into other tools
    $xml_array = []; 
    $xml_array['skills'] = [];
    $xml_array['name'] = str_replace('_', ' ', $character->charactername);
    $xml_array['characterID'] = $character->characterid;
    $xml_array['securityStatus'] = $security_status;
    $xml_array['attributes'] = ['intelligence' => $attributes->intelligence, 'memory' => $attributes->memory, 'perception' => $attributes->perception, 'willpower' => $attributes->willpower, 'charisma' => $attributes->charisma];
    foreach ($skillMap as $skill) {
      array_push($xml_array['skills'], 
        ['skill' => 
          ['_attributes' => 
            ['typeID' => "$skill->type_id", 
             'name' => $skill->skill_name,
             'level' => $skill->trained_level,
             'activelevel' => $skill->trained_level,
             'skillpoints' => str_replace(',', '', $skill->skillpoints),
             'ownsBook' => true,
             'isKnown' => true],
          ],
        ]);
    }
    $converted = ArrayToXml::convert($xml_array, 'SerializableCCPCharacter');
    return $converted;
  }

  public function getSkillsnMore($character) {
    #The GIANT catch all do everything function
    # $resp was a giant object that was eventually cached using the characterid as they cache key
    $alert = new \stdClass();
    $noauth_headers = [
      'headers' => [
        'User-Agent' => env('USERAGENT'),
      ],
    ];

    $auth_headers = [
      'headers' => [
        'User-Agent' => env('USERAGENT'),
        'Authorization' => "Bearer $character->access_token"
      ],
    ];

    //In the case of the structures endpoint when a character has lost ACL access where
    // a JC is located, just continue and put in a warning name for the station
    $no_exception_headers = [
      'http_errors' => false,
      'headers' => [
        'User-Agent' => env('USERAGENT'),
        'Authorization' => "Bearer $character->access_token"
      ],
    ];


    $handler_stack = HandlerStack::create();
    $handler_stack->push(\GuzzleHttp\Middleware::retry(function($retry, $request, $value, $reason) {
      // If we have a value already, we should be able to proceed quickly.
      $sc = $value->getStatusCode();
      if ($value !== NULL && $sc != 502) {
        return FALSE;
      }
      if($sc == 502) {
        Log::debug('Retrying 502');
      }
      // Reject after 1 additional retries.
      return $retry < 1;
      }));


    $client = new Client(['base_uri' => env('CCP_URL'), 'handler' => $handler_stack]);

    //Async requests
    $promises = [
      'skills' => $client->getAsync("/v4/characters/$character->characterid/skills/", $auth_headers),
      'public_info' => $client->getAsync("/v5/characters/$character->characterid/", $noauth_headers),
      'implants' => $client->getAsync("/v1/characters/$character->characterid/implants/", $auth_headers),
      'attributes' => $client->getAsync("/v1/characters/$character->characterid/attributes/", $auth_headers),
      'standings' => $client->getAsync("/v1/characters/$character->characterid/standings/", $auth_headers),
      'history' => $client->getAsync("/v1/characters/$character->characterid/corporationhistory/", $noauth_headers)
    ];

    if($character->jc_queue == TRUE) {
      #As I added more options as time went on, I had to account for characters that signed up when this functionality didn't exist
      $promises['skill_in_training'] = $client->getAsync("/v2/characters/$character->characterid/skillqueue/", $auth_headers);
      $promises['jump_clones'] = $client->getAsync("/v3/characters/$character->characterid/clones/", $auth_headers);
    }

    try {
      $results = Promise\unwrap($promises);
      $resp = json_decode($results['skills']->getBody());
      $charResp = json_decode($results['public_info']->getBody());
      $implantResp = json_decode($results['implants']->getBody());
      $attrResp = json_decode($results['attributes']->getBody());
      $standingsResp = json_decode($results['standings']->getBody());
      $historyResp = json_decode($results['history']->getBody());

    if($character->jc_queue == TRUE) {
      $skillTrainResp = json_decode($results['skill_in_training']->getBody());
      $jumpCloneResp = json_decode($results['jump_clones']->getBody());
    }

      Log::Debug("Starting Refresh $charResp->name");
      //Get Public Corporation Info
      $endpoint = "/v4/corporations/$charResp->corporation_id/";
      $corpRes = $client->get($endpoint, $noauth_headers);
      $corpResp = json_decode($corpRes->getBody());

      //Get Public Alliance Info if alliance exists
      if(isset($corpResp->alliance_id)) {
        $endpoint = "/v3/alliances/$corpResp->alliance_id/";
        $allyRes = $client->get($endpoint, $noauth_headers);
        $allyResp = json_decode($allyRes->getBody());
        $resp->{'alliance_id'} = $corpResp->alliance_id;
        $resp->{'alliance'} = $allyResp->name;
      }

      //Set Implant info if exists
      if(count($implantResp) > 0) {
        $implants = array();
        foreach($implantResp as $imp) {
          # mysql2 is the SDE
          $ret = \DB::connection('mysql2')->select("SELECT typeName AS imp_name FROM invTypes WHERE typeID = $imp");
          if(isset($ret) && isset($ret[0]->imp_name)) {
            array_push($implants, $ret[0]->imp_name);
          }
        }
        $resp->{'implants'} = $implants;
      }

      $factionStandings = array();
      $corpStandings = array();

      //Set Standings if present
      if(count($standingsResp) > 0) {
        foreach($standingsResp as $st) {
          if($st->from_type == 'faction') {
            # Faction was a table that contained relevant info about all factions
            $ret = Faction::where('faction_id', $st->from_id)->first();
            array_push($factionStandings, array('from_id' => $st->from_id, 'from_type' => $st->from_type, 'faction_name' => $ret->faction_name, 'standing' => $st->standing));

          } elseif($st->from_type == 'npc_corp') {
          # NpcCorp was a table that contained relevant info about all the npc corps, if it didn't exit we looked it up
            $retC = NpcCorp::where('corp_id', $st->from_id)->first();
            if(!isset($retC->corp_name)) {
              //Get Public Corporation Info for all NPC corps
              $endpoint = "/v4/corporations/$st->from_id/";
              $corpRes = $client->get($endpoint, $noauth_headers);
              $corpResp = json_decode($corpRes->getBody());
              $newCorp = new NpcCorp;
              $newCorp->corp_id = $st->from_id;
              $newCorp->corp_name = $corpResp->name;
              $newCorp->save();

              array_push($corpStandings, array('from_type' => $st->from_type, 'standing' => $st->standing, 'from_id' => $st->from_id, 'name' => $corpResp->name));
            } else {
              array_push($corpStandings, array('from_type' => $st->from_type, 'standing' => $st->standing, 'from_id' => $st->from_id, 'name' => $retC->corp_name));
            }
          } else {
            //Don't care about Agents for now
            continue;
          }
        }
      usort($factionStandings, function($a, $b) { return($a['standing'] > $b['standing']) ? -1 : 1;});
      usort($corpStandings, function($a, $b) { return($a['standing'] > $b['standing']) ? -1 : 1;});
      }

      $resp->{'in_training'} = NULL;
      //Build Skill in training
      if(isset($skillTrainResp) && count($skillTrainResp) > 0) {
        for($i = 0; $i < count($skillTrainResp); $i++) {
          $skillinfo = Typeid::where('type_id', $skillTrainResp[$i]->skill_id)->first();
          $finish_date = isset($skillTrainResp[$i]->finish_date) ? $skillTrainResp[$i]->finish_date : NULL; 
          $time_left = 'Training Paused';

          if(!is_null($finish_date)) {
            $finish_date = new \DateTime($skillTrainResp[$i]->finish_date); 
            $now = new \DateTime();
            $diff = date_diff($now, $finish_date);

            if($diff->invert == 1) {
              //This skillqueue only updates once they login to remove completed skills
              // So we have to check ourselves if its completed and check the next one
              continue;
            }

            $time_left = new Carbon($skillTrainResp[$i]->finish_date);
            $time_left = $time_left->diffForHumans();
            $finish_date = $finish_date->format('Y-m-d H:i:s');
          }

          switch ($skillTrainResp[$i]->finished_level) {
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
            case 5;
              $level = 'V';
              break;
            default;
              $level = 'Unknown';
              break;
          }
          $resp->{'in_training'} = 
            (object)array('skill_name' => $skillinfo->skill_name,
                          'finish_date' => $finish_date,
                          'time_left' => $time_left,
                          'levelroman' => $level,
                          'levelnum' => $skillTrainResp[$i]->finished_level
                         );
 
          break;
        }
      }

      //Build Jump Clones
      if(isset($jumpCloneResp) && count($jumpCloneResp->jump_clones) > 0) {
        $jump_clones = array();
        foreach($jumpCloneResp->jump_clones as $jc) {
          $clone = new \stdClass();
          $implants = array();
          
          if($jc->location_type == 'station' && $jc->location_id != 0) {
            //NPC STATION
            $endpoint = "/v2/universe/stations/$jc->location_id/";
            $station = $client->get($endpoint, $noauth_headers);
            $stationResp = json_decode($station->getBody());
            $clone->station_name = $stationResp->name;
          }

          if($jc->location_type == 'structure') {
            $endpoint = "/v1/universe/structures/$jc->location_id/";
            $station = $client->get($endpoint, $no_exception_headers);

            $stationResp = json_decode($station->getBody());
            if(isset($stationResp->name)) {
              $clone->station_name = $stationResp->name;
            } else {
              $clone->station_name = "Unknown Station -- Character cannot dock here";
            }
          }

          foreach($jc->implants as $imp) {
            $ret = \DB::connection('mysql2')->select("SELECT typeName AS imp_name FROM invTypes WHERE typeID = $imp");
            if(isset($ret) && isset($ret[0]->imp_name)) {
              array_push($implants, $ret[0]->imp_name);
            }
          }
          $clone->location_id = $jc->location_id;
          $clone->implants = $implants;
          $clone->location_type = $jc->location_type;

          array_push($jump_clones, $clone);
        }
        $resp->{'jump_clones'} = $jump_clones;

      } else {
        $resp->{'jump_clones'} = NULL;
      }

      //Build Corp History
      usort($historyResp, array("static","cmp"));
      $cHist = array();
      for ($i=0; $i < count($historyResp); $i++) {
        $cID = $historyResp[$i]->corporation_id;
        $endpoint = "/v4/corporations/$cID/";
        $cHistoryRes = $client->get($endpoint, $noauth_headers);
        $cHistoryResp = json_decode($cHistoryRes->getBody());
        $d = $i;
        $d++;
        if(count($historyResp) > 0 && isset($historyResp[$d]->start_date)) {
          $ee_date = new \DateTime($historyResp[$d]->start_date);
          $ee_date->sub(new \DateInterval('PT1S'));
          $e_date = $ee_date->format('Y-m-d H:i');
          $ss_date = new \DateTime($historyResp[$i]->start_date);
          $s_date = $ss_date->format('Y-m-d H:i');
          $total_days = date_diff($ss_date, $ee_date);
          $last = null;
        } else {
          $ss_date = new \DateTime($historyResp[$i]->start_date);
          $s_date = $ss_date->format('Y-m-d H:i');
          $ee_date = new \DateTime();
          $e_date = $ee_date->format('Y-m-d H:i');
          $total_days = date_diff($ss_date, $ee_date);
          $last = TRUE;
        }
        if(!isset($cHistoryResp->name)) {
          $cHistoryResp = new \stdClass(); 
          $cHistoryResp->name = "(Failed) Unknown Corp Name";
        }
        array_push($cHist,
            array('name' => $cHistoryResp->name,
              'start_date' => $s_date,
              'end_date' => $e_date,
              'total' => $total_days->days,
              'last' => $last)
            );
      }

      //Calculate Yearly Remap
      if(isset($attrResp->accrued_remap_cooldown_date)) {
        $yearly_remap_date= new \DateTime($attrResp->accrued_remap_cooldown_date); 
        $now = new \DateTime();
        $remap_diff = date_diff($now, $yearly_remap_date);
        if($remap_diff->invert == 1) {
          //This skillqueue only updates once they login to remove completed skills
          // So we have to check ourselves if its completed and check the next one
          $yearly_remap = 1;
        } else {
          $yearly_remap = new Carbon($attrResp->accrued_remap_cooldown_date);
          $yearly_remap = $yearly_remap->diffForHumans();
        }
      } else {
        //They have never used one, so its probably there
        $yearly_remap = 1; 
      }

      #Setting of all the object properties that are referenced in the html files for building the views 
      $resp->{'yearly_remap'} = $yearly_remap;
      $resp->{'factionStandings'} = $factionStandings;
      $resp->{'corpStandings'} = $corpStandings;
      $resp->{'attributes'} = $attrResp;
      $resp->{'corpHistory'} = array_reverse($cHist);
      $resp->{'birthday'} = strstr($charResp->birthday, 'T', true);
      $resp->{'corporation'} = $corpResp->name;
      $resp->{'corporation_id'} = $charResp->corporation_id;
      $resp->{'security'} = $charResp->security_status;
      $resp->{'skillMap'} = $this->mapSkills($resp->skills);
      $resp->{'skillGroupMap'} = $this->mapSkillGroups();
      $resp->{'cName'} = $charResp->name;
      $resp->{'cID'} = $character->characterid;
      $resp->{'shipGroupMap'} = $this->shipGroups();
      $resp->{'races'} = $this->raceID();
      $resp->{'xml'} = $this->mapXML($character, $charResp->security_status, $resp->skillMap, $attrResp);

      # Laravel file cache
      if(Cache::has($character->characterid)) {
        $cache = Cache::get($character->characterid);
        if($cache->total_sp != $resp->total_sp || !isset($cache->fly)) {
          //only check this if SP has changed
          $resp->{'fly'} = $this->canFly($resp->{'skillMap'}, $resp->{'attributes'});
        } else {
          $resp->{'fly'} = $cache->fly;
        }
      } else { 
          //check this if we don't have a cache
          $resp->{'fly'} = $this->canFly($resp->{'skillMap'}, $resp->{'attributes'});
      }
      
      $character->total_sp = $resp->total_sp;

      //Calculate extractable SP including unallocated
      $unallocated = isset($resp->unallocated_sp) ? $resp->unallocated_sp : 0;
      $total_extractable = $character->total_sp + $unallocated;
      if($total_extractable > 5500000) {
        $resp->{'total_injectors'} = floor(($total_extractable - 5000000) / 500000);
      } else {
        $resp->{'total_injectors'} = 0;
      }

      $character->token_failures = 0;
      $character->save();

      Cache::forget($character->characterid . "_error");
      Cache::forever($character->characterid, $resp);

      Log::Debug("Finished Refresh of $charResp->name");

      return $resp;

    } catch (ClientException $e) {
      //4xx error, usually encountered when token has been revoked on CCP website
      $msg = "We failed to fetch the skills.".
        " We received a 4xx error which usually means the access has been revoked by the owner. Please contact the owner to reauth.";
      $alert->{'exception'} = $msg;
      Log::error("ClientException in skills update: " . $e->getMessage());
      return $alert;
    } catch (ServerException $e ) {
      //5xx error, usually and issue with ESI
      $msg = "We failed to fetch the skills. ".
        " We received a 5xx error which usually means ESI is having issues. Please try again later.";
      $alert->{'exception'} = $msg;
      Log::error("ServerException in skills update: " . $e->getMessage());
      return $alert;
    } catch (\Exception $e) {
      //Everything else
      $msg = "We failed to fetch the skills, please try again later";
      $alert->{'exception'} = $msg;
      Log::error("Exception in skills update: " . $e->getMessage());
      return $alert;
    }

  }

  private static function cmp($a, $b) {
    # Sorting function
    if ($a->record_id == $b->record_id) {
      return 0;
    }
    return ($a->record_id < $b->record_id) ? -1 : 1;
  }

}

