{{-- Renders a single section (grid or legacy columns) --}}
@php
    $settings = $section['settings'] ?? [];
    $padding = $settings['padding'] ?? 'py-12 px-6';

    // Build inline style for section background/text color
    $sectionStyle = '';
    if (!empty($settings['bgColor'])) {
        $sectionStyle .= 'background-color: ' . e($settings['bgColor']) . ';';
    }
    if (!empty($settings['bgImage'])) {
        $sectionStyle .= "background-image: url('" . asset('storage/' . $settings['bgImage']) . "'); background-size: cover; background-position: center;";
    }
    if (!empty($settings['textColor'])) {
        $sectionStyle .= 'color: ' . e($settings['textColor']) . ';';
    }
@endphp
<section style="{{ $sectionStyle }}" class="{{ $padding }}">
    @if(!empty($section['grid']) && !empty($section['gridConfig']))
        {{-- CSS Grid layout --}}
        @php
            $gridConfig = $section['gridConfig'];
            $cols = $gridConfig['cols'] ?? 1;
            $colWidths = $gridConfig['colWidths'] ?? array_fill(0, $cols, '1fr');
            $rows = count($section['grid']);
            $gap = $settings['gap'] ?? '1.5rem';
            $rowHeight = $settings['rowHeight'] ?? 'auto';
            $rowTemplate = $rowHeight === 'auto' ? 'minmax(100px, auto)' : $rowHeight;

            $gridStyle = sprintf(
                'display: grid; grid-template-columns: %s; grid-template-rows: repeat(%d, %s); gap: %s;',
                implode(' ', $colWidths),
                $rows,
                $rowTemplate,
                $gap
            );
        @endphp
        <div style="{{ $gridStyle }}">
            @foreach($section['grid'] as $rowIndex => $row)
                @foreach($row as $colIndex => $cell)
                    @php
                        $cellStyle = '';
                        if (($cell['colSpan'] ?? 1) > 1) {
                            $cellStyle .= 'grid-column: span ' . $cell['colSpan'] . ';';
                        }
                        if (($cell['rowSpan'] ?? 1) > 1) {
                            $cellStyle .= 'grid-row: span ' . $cell['rowSpan'] . ';';
                        }
                    @endphp
                    <div style="{{ $cellStyle }}" class="space-y-4">
                        @foreach($cell['blocks'] ?? [] as $block)
                            @if(view()->exists('pages.publiek.partials.blocks.' . ($block['type'] ?? '')))
                                @include('pages.publiek.partials.blocks.' . $block['type'], ['block' => $block])
                            @endif
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    @else
        {{-- Legacy column layout --}}
        @php
            $layout = $section['layout'] ?? 'full';
            $columnClasses = match($layout) {
                'two-cols' => 'md:grid-cols-2',
                'two-cols-left' => 'md:grid-cols-[2fr_1fr]',
                'two-cols-right' => 'md:grid-cols-[1fr_2fr]',
                'three-cols' => 'md:grid-cols-3',
                'four-cols' => 'md:grid-cols-4',
                'sidebar-left' => 'md:grid-cols-[250px_1fr]',
                'sidebar-right' => 'md:grid-cols-[1fr_250px]',
                default => 'grid-cols-1',
            };
            $gap = $settings['gap'] ?? '1.5rem';
        @endphp
        <div class="grid {{ $columnClasses }}" style="gap: {{ $gap }}">
            @foreach($section['columns'] ?? [] as $column)
                <div class="space-y-4">
                    @foreach($column['blocks'] ?? [] as $block)
                        @if(view()->exists('pages.publiek.partials.blocks.' . ($block['type'] ?? '')))
                            @include('pages.publiek.partials.blocks.' . $block['type'], ['block' => $block])
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</section>
