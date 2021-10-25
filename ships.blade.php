<br>
<div class="text-center">
  <p><i style="color:red" class="fa fa-exclamation-circle" aria-hidden="true"></i> Click a ship to see missing requirements (if any) <i style="color:red" class="fa fa-exclamation-circle" aria-hidden="true"></i> <p>
  <div class="btn-group">
  <!-- JS sorting for known ships, unknown ships, all ships -->
    <button type="button" class="btn btn-primary ships_all">Show All</button>
    <button type="button" class="btn btn-success ships_fly">Can Fly</button>
    <button type="button" class="btn btn-danger ships_notfly">Cannot Fly</button>
  </div>

</div>
<br />
  <select class="form-control center-block" id="ship-dropdown" style="width:150px">
      <option>Jump to Ship</option>
    @foreach($shipArray as $shp)
      <option value="#{{str_replace(' ', '_', $shp)}}">{{$shp}}</option>
    @endforeach
  </select>
  @foreach($skillEntry->shipGroupMap as $group)
  @if($group->groupName == 'Elite Battleship' || $group->groupName == 'Citizen Ships')
  <!-- skip unflyable ships, test ships -->
    @continue
  @endif
  <table class="table-condensed" style="max-width:100%">
    <thead>
      <h4 style="font-weight:700;"> {{ $group->groupName }}</h4>
      <div class="containerfluid" style="border-bottom:1px solid white"></div>
    </thead>
    <tbody>
    <!-- map each ship to its race and group (frigate, battleship etc) -->
    @foreach($skillEntry->fly as $shipType => $val)
      @if($shipType == $group->groupName)
        @foreach($skillEntry->races as $race)
        <tr>
          @php $count = 1 @endphp
          @foreach($val as $shipName => $v)
            @if($v->raceID == $race->raceid)
              @if($count == 1)
              <td >
                <img src="{{ asset('/images/ccp') }}/race_{{$v->raceID}}.png">
              </td>
              @endif
            @php $count++ @endphp
              <td id="{{str_replace(' ', '_', $shipName)}}">
                <div class="btn-group btn-default">
                  <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img src="{{ asset('/images/loading.gif') }}" data-src="https://images.evetech.net/types/{{ $v->shipTypeID }}/icon?size=64" title="{{$shipName}}" class="lazy @if(isset($v->missing))grey_img @else can_fly @endif" >
                  </a>
                  <ul class="dropdown-menu" style="padding:5px;min-width:max-content">
                    <li style="text-align:center"><strong>{{$shipName}}</strong></li>
                    @if(isset($v->missing))
                    <li style="text-align:center">{{$v->total_time_missing ?? ''}}</li>
                    <li><strong>Missing Skills:</strong></li>
                    @foreach($v->missing as $m)
                      <li>{{$m}}</li>
                    @endforeach
                    @endif
                </ul>
                </div>
              </td>
            @endif
          @endforeach
        </tr>
        @endforeach
      @endif
    @endforeach
    </tbody>
  </table>
@endforeach


