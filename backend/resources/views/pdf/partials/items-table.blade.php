@php
    /**
     * Tabla de conceptos. Se reutiliza en las paginas de continuacion, por eso
     * recibe el tramo de filas y el indice de arranque en vez de leer
     * $invoice->items directamente.
     *
     * @var \Illuminate\Support\Collection $rows
     * @var int    $startIndex   Numero de la primera fila de este tramo (base 1).
     * @var string $caption
     * @var string $descriptionHeading
     * @var object $money        CurrencyFormatterService
     * @var array  $currency
     */
@endphp
<div>
    <div class="items-caption">
        <x-pdf-icon name="wrench" :size="9" />
        {{ $caption }}
    </div>
    <table class="items">
        <thead>
            <tr>
                <th class="c-idx">Nº</th>
                <th>{{ $descriptionHeading }}</th>
                <th class="c-qty">Cant.</th>
                <th class="c-price">Precio unit.</th>
                <th class="c-amount">Importe</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $offset => $item)
            <tr>
                <td class="c-idx">{{ $startIndex + $offset }}</td>
                <td><div class="desc">{{ $item->description }}</div></td>
                <td class="c-qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                <td class="c-price nowrap">{{ $money->format($item->unit_cost, $currency) }}</td>
                <td class="c-amount nowrap">{{ $money->format($item->line_subtotal, $currency) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
