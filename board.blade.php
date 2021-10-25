<div class="text-center">
Filter Skills:
<!-- javascript buttons for hiding/sorting skills -->
  <div class="btn-group">
    <button type="button" class="btn btn-primary btn-xs skill_all" >Show All</button>
    <button type="button" class="btn btn-primary btn-xs unknown_all" >Not Injected</button>
    <button type="button" class="btn btn-primary btn-xs injected" >Injected</button>
    <button type="button" class="btn btn-primary btn-xs skill_5" >5</button>
    <button type="button" class="btn btn-primary btn-xs skill_4" >4</button>
    <button type="button" class="btn btn-primary btn-xs skill_3" >3</button>
    <button type="button" class="btn btn-primary btn-xs skill_2" >2</button>
    <button type="button" class="btn btn-primary btn-xs skill_1" >1</button>
    <button type="button" class="btn btn-primary btn-xs skill_0" >0</button>
  </div>
</div>


<!-- Build the skill page with the cool boxes -->
@foreach($skillEntry->skillGroupMap as $group)
<!-- The top of the loop from the groups of skills, associate skills to a group based off the function we used to calculate training time etc -->
  @if($group->group_name == 'Fake Skills' || !isset($group->group_name))
    @php
    <!-- skip skills we don't care about -->
    {{ continue; }}
    @endphp
  @endif
  @php $countFive = 0; $count = 0; $fiveSP = 0; $skillSP = 0; $notinjected = 0@endphp
  <table class="table table-condensed">
    <thead><img src="{{ asset('/images/ccp') }}/{{ $group->group_name}}.png" height="5%" width="5%" style="display:inline-block"> <h4 style="font-weight:700; display:inline-block; vertical-align:middle"> {{$group->group_name}}</h4></thead>
    <tbody>
    @foreach($skillEntry->skillMap as $skill)
    @if($skill->group_name == $group->group_name)
    <!-- group skills together -->
      @if($skill->trained_level == 99)
      <tr class="level_99 unknown_skill" style="display: none;">
      @else
      <tr class="level_{{$skill->trained_level}} known_skill">
      @endif
        <td @if(isset($skillEntry->in_training->skill_name) && $skillEntry->in_training->skill_name == $skill->skill_name && !$character->skill_queue_hide) style="color:orange" @endif>
          @if($skill->trained_level == 5)
            @php $countFive++;$fiveSP += str_replace(',', '', $skill->skillpoints); @endphp
            <span class="level_5">
          @endif
          @if($skill->trained_level == 99)
          {{ $skill->skill_name }} / Rank {{$skill->rank}} / NOT INJECTED
          @php $notinjected++; @endphp
          @else
          {{ $skill->skill_name }} / Rank {{$skill->rank}} / Level: {{$skill->trained_level}} / SP: {{ $skill->skillpoints}} of {{ $skill->skill_max_sp }}
          @php $count++; $skillSP += str_replace(',', '', $skill->skillpoints); @endphp
          @endif
          @if(isset($skillEntry->in_training->skill_name) && $skillEntry->in_training->skill_name == $skill->skill_name && !$character->skill_queue_hide) --- Currently Training to {{$skillEntry->in_training->levelnum}} @endif
          @if($skill->trained_level == 5)
            </span>
          @endif
        </td>
        <td>
          <!-- build the boxes -->
          <span class="pull-right">
            @if($skill->trained_level == 99)
              @for($i = 0; $i < 5; $i++)
                <span class="far fa-minus-square squares" aria-hidden="false"></span>
              @endfor
            @else
              @for($i = 0; $i < $skill->trained_level; $i++)
                <span class="fas fa-square squares" aria-hidden="false"></span>
              @endfor
              @for($i = 0 ; $i < (5 - $skill->trained_level); $i++)
                <span class="far fa-square squares" aria-hidden="false"></span>
              @endfor
            @endif
          </span>
        </td>
      </tr>
    @endif
    @endforeach
      <tr>
        <td>
        @if($count > 0)
          <ul>
            <li>{{$count}} {{$group->group_name}} skills trained for a total of {{ number_format($skillSP) }} skillpoints</li>
            <li>{{$countFive}} skills trained to level 5 for a total of {{ number_format($fiveSP) }} skillpoints</li>
            <li>{{$notinjected}} skills not injected</li>
          </ul>
        @endif
        </td>
      </tr>
    </tbody>
  </table>
@endforeach
