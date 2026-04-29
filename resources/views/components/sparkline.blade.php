@props([
    'points' => [],
    'width' => 160,
    'height' => 36,
    'color' => '#3b82f6',
    'fill' => true,
    'min' => null,
    'max' => null,
])

@php
    $values = collect($points)->filter(fn ($v) => is_numeric($v))->values()->all();

    if (count($values) < 2) {
        $svg = null;
    } else {
        $minV = $min !== null ? (float) $min : min($values);
        $maxV = $max !== null ? (float) $max : max($values);
        $range = $maxV - $minV;
        if ($range <= 0) {
            $range = 1;
        }
        $stepX = $width / (count($values) - 1);

        $coords = [];
        foreach ($values as $i => $v) {
            $x = $i * $stepX;
            $y = $height - ((($v - $minV) / $range) * ($height - 2)) - 1;
            $coords[] = round($x, 2).','.round($y, 2);
        }

        $linePath = 'M '.implode(' L ', $coords);
        $fillPath = $linePath.' L '.round($width, 2).','.$height.' L 0,'.$height.' Z';

        $last = end($values);
        $lastX = ($width - 1);
        $lastY = $height - ((($last - $minV) / $range) * ($height - 2)) - 1;
        $svg = compact('linePath', 'fillPath', 'lastX', 'lastY');
    }
@endphp

@if ($svg)
    <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none"
        width="{{ $width }}" height="{{ $height }}" {{ $attributes }}>
        @if ($fill)
            <path d="{{ $svg['fillPath'] }}" fill="{{ $color }}" fill-opacity="0.15" />
        @endif
        <path d="{{ $svg['linePath'] }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="{{ $svg['lastX'] }}" cy="{{ $svg['lastY'] }}" r="2" fill="{{ $color }}" />
    </svg>
@else
    <div class="text-[10px] text-gray-400 dark:text-neutral-600 italic">no data</div>
@endif
