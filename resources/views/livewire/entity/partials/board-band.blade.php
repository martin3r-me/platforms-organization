<div class="flex-1 min-h-[60px] px-2 py-1.5 rounded-lg mb-1" style="background:{{ $band['color'] }}08;border-left:3px solid {{ $band['color'] }}40" data-band-code="{{ $code }}">
    <div class="flex items-center gap-2 mb-1">
        <span class="text-[10px] uppercase tracking-wider font-bold" style="color:{{ $band['color'] }}">{{ $band['label'] }}</span>
        <span class="text-[9px] text-gray-600 tabular-nums">({{ count($band['entities']) }})</span>
    </div>
    @if(count($band['entities']) > 0)
        <div class="flex flex-nowrap gap-2 overflow-x-auto pb-1">
            @foreach($band['entities'] as $ent)
                @include('organization::livewire.entity.partials.board-card', ['ent' => $ent, 'bandColor' => $band['color']])
            @endforeach
        </div>
    @else
        <div class="text-[10px] text-gray-600 italic px-1">Keine Einheiten zugeordnet</div>
    @endif
</div>
