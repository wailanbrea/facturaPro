@php
    /**
     * Ultima hoja: condiciones en dos filas de cinco bloques.
     *
     * @var array<int, array{icon:string, tone:string, title:string, text:string}> $blocks
     */
@endphp
<div class="legal-grid">
    @foreach($blocks as $index => $block)
        <div class="legal-item">
            <div class="legal-ico {{ $block['tone'] ?? 'navy' }}">
                <x-pdf-icon :name="$block['icon']" :size="18" />
            </div>
            <div>
                <div class="legal-title">{{ $index + 1 }}. {{ $block['title'] }}</div>
                <div class="legal-text">{{ $block['text'] }}</div>
            </div>
        </div>
    @endforeach
</div>
