@extends('layouts.app')

@use('App\Support\Taka')
@use('App\Support\Weight')
@use('App\Support\Asset')

@php
    $steps = [1 => __('Cake & size'), 2 => __('Flavour & colours'), 3 => __('Decorate'), 4 => __('Design the top'), 5 => __('Date & time'), 6 => __('Review')];
    $library = $client['library'] ?? [];
    $price = fn (int $paisa) => $paisa > 0 ? '+'.Taka::format($paisa) : __('Included');
@endphp

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol>
        <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
        <li aria-current="page">{{ __('Custom cake') }}</li>
    </ol></nav>

    <header class="cake-head">
        <h1>{{ $readOnly ? __('A shared cake design') : __('Design your custom cake') }}</h1>
        <p class="muted">{{ $readOnly
            ? __('Someone shared this design with you. Photos are private and are not included. Start your own copy to change it and order.')
            : __('Six short steps: size, flavour and colours, decorations, your own top design, a date, then review. The price updates line by line as you go.') }}</p>
    </header>

    @if ($cake === null)
        <div class="card empty-state"><x-icon name="cake" /><p><strong>{{ __('Custom cakes are not available yet.') }}</strong></p>
            <p class="muted">{{ __('Please check back soon.') }}</p></div>
    @else
        @if (! $ordersOpen && ! $readOnly)
            <div class="alert alert-info" role="status" style="margin-bottom:16px"><x-icon name="info" /><span>{{ __('Custom cake orders are closed right now. You can still explore designs and prices.') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert" style="margin-bottom:16px"><x-icon name="alert" /><span>{{ $errors->first() }}</span></div>
        @endif

        @php
            $cakeData = $client + ['i18n' => [
            'editorLabel' => __('Cake top design area'), 'selected' => __('Selected'), 'text' => __('Text'), 'photo' => __('Photo'), 'added' => __('Added'), 'removed' => __('Removed'),
            'moveUp' => __('Move forward'), 'moveDown' => __('Move back'), 'duplicate' => __('Duplicate'), 'delete' => __('Delete'), 'noLayers' => __('Nothing on the top yet. Add text, a photo or something from the library.'),
            'selectHint' => __('Select something on the cake top (or in the layer list) to change it.'), 'rotate' => __('Rotate'), 'textLabel' => __('Text'), 'font' => __('Font'), 'colour' => __('Colour'),
            'outline' => __('Outline colour'), 'outlineWidth' => __('Outline width'), 'size' => __('Size'), 'curve' => __('Curve'), 'cropZoom' => __('Crop (zoom in)'), 'cropX' => __('Crop left ↔ right'),
            'cropY' => __('Crop top ↕ bottom'), 'brightness' => __('Brightness'), 'circleMask' => __('Circle shape'), 'free' => __('Free'), 'noResults' => __('Nothing found. Try another word.'),
            'replaceWithTemplate' => __('Replace the current top design with this template? Your photos stay.'), 'rightsFirst' => __('Please tick the box to confirm you own this photo or have permission to print it.'),
            'tooManyPhotos' => __('You can use up to :n photos on one cake.', ['n' => $client['maxImages'] ?? 6]), 'tooManyLayers' => __('That is the most items one cake top can hold.'),
            'uploadFailed' => __('The photo could not be uploaded. Please try again.'), 'sampleText' => __('Happy Birthday'),
            'quality' => ['good' => __('Sharp print'), 'fair' => __('OK print, may look slightly soft'), 'low' => __('Too small to print sharply: make it smaller or use a larger photo')],
            'view3d' => __('3D view'), 'view2d' => __('2D drawing'), 'topView' => __('Top'), 'previewLabel' => __('Preview of your cake. Drag to turn it, use the buttons to zoom or change the view.'),
            'previewFailed' => __('The 3D preview is not available on this device, so a 2D drawing is shown.'), 'across' => __('≈ :cm cm across'), 'serves' => __('about :n slices'),
            'tierCount' => __(':n tier(s)'), 'shareCopied' => __('Share link copied.'), 'shareReady' => __('Share link:'), 'customColour' => __('Custom colour'),
            'none' => __('None'), 'photos' => __('Photos'), 'textItems' => __('Text'), 'libraryItems' => __('Library items'), 'dateMissing' => __('Not chosen yet'), 'priceFailed' => __('The price could not be updated just now. Please try again in a moment.'),
        ], 'locale' => app()->getLocale()];
        @endphp

        <form method="POST" action="{{ route('cakes.store') }}" class="cake-studio" id="cake-form" data-price-url="{{ route('cakes.price') }}" data-config="{{ json_encode($cakeData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
              data-three="{{ Asset::url('vendor/three/three.module.min.js') }}" data-konva="{{ Asset::url('vendor/konva/konva.min.js') }}"
              data-preview3d="{{ Asset::url('js/cake/preview3d.js') }}" data-preview2d="{{ Asset::url('js/cake/preview2d.js') }}" data-editor="{{ Asset::url('js/cake/editor.js') }}"
              @if ($readOnly) data-readonly="1" @endif novalidate>
            @csrf
            @if ($design)<input type="hidden" name="design" value="{{ $design->uuid }}">@endif
            <input type="hidden" name="canvas" id="canvas-input" value="{{ json_encode(['layers' => $spec['canvas']['layers'] ?? []]) }}">
            <input type="hidden" name="template" id="template-input" value="{{ $spec['template'] ?? '' }}">
            <input type="hidden" name="snapshot" id="snapshot-input">
            <input type="hidden" name="top_image" id="top-input">

            <div class="cake-left">
            <div class="cake-stage" id="cake-stage">
                <div class="cake-viewer" id="cake-viewer" aria-live="off">
                    <div class="cake-viewer-loading"><x-icon name="cake" /><span>{{ __('Preparing the preview…') }}</span></div>
                </div>
                <div class="viewer-tools" role="group" aria-label="{{ __('Preview controls') }}" hidden>
                    <button type="button" class="btn btn-outline btn-sm" data-view="3d" aria-pressed="true">{{ __('3D') }}</button>
                    <button type="button" class="btn btn-outline btn-sm" data-view="front" aria-pressed="false">{{ __('Front') }}</button>
                    <button type="button" class="btn btn-outline btn-sm" data-view="top" aria-pressed="false">{{ __('Top') }}</button>
                    <button type="button" class="icon-btn" data-zoom="1" aria-label="{{ __('Zoom in') }}">+</button>
                    <button type="button" class="icon-btn" data-zoom="-1" aria-label="{{ __('Zoom out') }}">−</button>
                    <button type="button" class="btn btn-ghost btn-sm" data-mode-toggle>{{ __('2D drawing') }}</button>
                </div>
                <p class="viewer-size small muted" id="viewer-size" aria-live="polite"></p>
                <noscript><p class="small muted">{{ __('Turn on JavaScript to see the live 3D preview and design the top.') }}</p></noscript>
            </div>
            <aside class="card cake-summary" aria-labelledby="sum-title">
                <h2 id="sum-title">{{ __('Your cake') }}</h2>
                <ul class="cake-breakdown" id="cake-breakdown" aria-live="polite">
                    @foreach ($priced['breakdown'] ?? [] as $part)<li><span>{{ $part['label'] }}</span><span class="tnum">{{ Taka::format($part['paisa']) }}</span></li>@endforeach
                </ul>
                <div class="cake-total"><span>{{ __('Cake price') }}</span><strong class="tnum" id="cake-total">{{ Taka::format($priced['line_paisa'] ?? 0) }}</strong></div>
                <p class="field-error" id="cake-error" @if (($priced['ok'] ?? false) || empty($priced['error'])) hidden @endif>{{ $priced['error'] ?? '' }}</p>
                <p class="hint">{{ __('Delivery is added at checkout from your area. An advance payment may be needed to confirm a custom cake.') }}</p>
                <p class="test-price-note" id="test-note" @unless ($priced['uses_test_prices'] ?? false) hidden @endunless>{{ __('TEST prices — development only, not final.') }}</p>
                <p class="hint">{{ __('The real cake is handmade and may vary slightly from the preview.') }}</p>
            </aside>
            </div>

            <div class="cake-flow">
                <nav class="cake-stepper" aria-label="{{ __('Designer steps') }}" hidden>
                    <ol>@foreach ($steps as $n => $label)<li><button type="button" data-goto="{{ $n }}" @if ($n === 1) aria-current="step" @endif><span class="num">{{ $n }}</span><span class="lbl">{{ $label }}</span></button></li>@endforeach</ol>
                </nav>

                @if ($cakes->count() > 1 && ! $readOnly)
                    <section class="card card-pad cake-step" data-step="1" aria-labelledby="s-cake">
                        <h2 id="s-cake">{{ __('Cake') }}</h2>
                        <div class="choice-grid">
                            @foreach ($cakes as $choice)
                                <label class="choice"><input type="radio" name="cake" value="{{ $choice->id }}" @checked($choice->id === $cake->id) data-reload="{{ route('cakes.designer', ['product' => $choice->product->slug]) }}"><span>{{ $choice->product->localized('name') }}</span></label>
                            @endforeach
                        </div>
                    </section>
                @else
                    <input type="hidden" name="cake" value="{{ $cake->id }}">
                @endif

                <section class="card card-pad cake-step" data-step="1" aria-labelledby="s-shape" tabindex="-1">
                    <h2 id="s-shape"><span class="step-no">1</span> {{ __('Cake & size') }}</h2>
                    <fieldset><legend>{{ __('Shape') }}</legend>
                        <div class="choice-grid">
                            @foreach ($options->get('shape', []) as $option)
                                <label class="choice choice-shape"><input type="radio" name="shape" value="{{ $option->code }}" @checked(($spec['shape'] ?? '') === $option->code)>
                                    <svg class="shape-ico" viewBox="0 0 40 40" aria-hidden="true" data-shape-icon="{{ $option->code }}"></svg>
                                    <span>{{ $option->localized('name') }}</span><small class="tnum">{{ $price($option->price_paisa) }}</small></label>
                            @endforeach
                        </div>
                        @if ($options->get('shape', collect())->firstWhere('code', 'number'))<p class="hint">{{ __('Number and letter cakes are previewed as a plaque; our baker cuts the exact number or letter.') }}</p>@endif
                    </fieldset>
                    <div class="form-grid">
                        <div class="field"><label for="grams">{{ __('Weight') }}</label>
                            <select class="input" id="grams" name="grams">
                                @foreach ($cake->weightChoices() as $grams)<option value="{{ $grams }}" @selected((int) ($spec['grams'] ?? 0) === $grams)>{{ Weight::format($grams) }}</option>@endforeach
                            </select>
                            <span class="hint" id="size-hint"></span></div>
                        <div class="field"><label for="tiers">{{ __('Tiers') }}</label>
                            <select class="input" id="tiers" name="tiers">
                                @for ($n = 1; $n <= $cake->max_tiers; $n++)
                                    @if ($n === 1 || $options->get('tier', collect())->firstWhere('code', (string) $n))
                                        <option value="{{ $n }}" @selected((int) ($spec['tiers'] ?? 1) === $n)>{{ trans_choice(':count tier|:count tiers', $n) }}</option>
                                    @endif
                                @endfor
                            </select></div>
                    </div>
                </section>

                <section class="card card-pad cake-step" data-step="2" aria-labelledby="s-flavour" tabindex="-1">
                    <h2 id="s-flavour"><span class="step-no">2</span> {{ __('Flavour & colours') }}</h2>
                    <fieldset><legend>{{ __('Flavour') }}</legend>
                        <div class="choice-grid">
                            @foreach ($options->get('flavour', []) as $option)
                                <label class="choice"><input type="radio" name="flavour" value="{{ $option->code }}" @checked(($spec['flavour'] ?? '') === $option->code)>
                                    <span>{{ $option->localized('name') }}</span><small class="tnum">{{ Taka::format($option->price_paisa) }}/kg</small></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="form-grid">
                        @foreach (['filling' => __('Filling'), 'cream' => __('Cream')] as $group => $label)
                            @if ($options->has($group))
                                <div class="field"><label for="{{ $group }}">{{ $label }}</label>
                                    <select class="input" id="{{ $group }}" name="{{ $group }}">
                                        <option value="">{{ __('Standard (included)') }}</option>
                                        @foreach ($options->get($group) as $option)<option value="{{ $option->code }}" @selected(($spec[$group] ?? '') === $option->code)>{{ $option->localized('name') }}</option>@endforeach
                                    </select></div>
                            @endif
                        @endforeach
                    </div>
                    @php
                        $diets = collect(['eggless' => $cake->allow_eggless, 'sugar_free' => $cake->allow_sugar_free])
                            ->filter()->keys()->filter(fn ($code) => $options->get('diet', collect())->firstWhere('code', $code));
                    @endphp
                    @if ($diets->isNotEmpty())
                        <fieldset><legend>{{ __('Dietary options') }}</legend>
                            @foreach ($diets as $code)
                                @php $dietOption = $options->get('diet')->firstWhere('code', $code); @endphp
                                <label class="check"><input type="checkbox" name="diets[]" value="{{ $code }}" @checked(in_array($code, $spec['diets'] ?? [], true))> {{ $dietOption->localized('name') }}</label>
                            @endforeach
                            @unless ($cake->allow_both_diets)<p class="hint">{{ __('Choose one dietary option per cake.') }}</p>@endunless
                        </fieldset>
                    @endif

                    <fieldset class="colour-set"><legend>{{ __('Cream colours') }}</legend>
                        @if (! empty($library['palette']))
                            <div class="palette-list" role="radiogroup" aria-label="{{ __('Colour palette') }}">
                                @foreach ($library['palette'] as $palette)
                                    <label class="palette"><input type="radio" name="palette" value="{{ $palette['code'] }}" @checked(($spec['palette'] ?? '') === $palette['code'])>
                                        <span class="palette-name">{{ $palette['name'] }} <small class="tnum">{{ $price($palette['price_paisa']) }}</small></span>
                                        <span class="swatches" aria-hidden="true">@foreach ($palette['payload']['colours'] as $c)<i style="background:{{ $c }}"></i>@endforeach</span></label>
                                @endforeach
                            </div>
                        @endif
                        <div class="form-grid">
                            <div class="field"><label for="base_colour">{{ __('Main cream colour') }}</label>
                                <div class="colour-pick"><input type="color" id="base_colour" name="base_colour" value="{{ $spec['base_colour'] ?: '#fffaf3' }}"><span class="swatch-row" data-swatches-for="base_colour"></span></div></div>
                            <div class="field"><label for="accent_colour">{{ __('Accent colour (piping, patterns)') }}</label>
                                <div class="colour-pick"><input type="color" id="accent_colour" name="accent_colour" value="{{ $spec['accent_colour'] ?: '#fffaf3' }}"><span class="swatch-row" data-swatches-for="accent_colour"></span></div></div>
                        </div>
                        <p class="hint">{{ $client['customColourPaisa'] !== null
                            ? __('Colours from the chosen palette are included. Any other colour adds :amount.', ['amount' => Taka::format($client['customColourPaisa'])])
                            : __('Choose colours from a palette.') }}</p>
                    </fieldset>
                </section>

                <section class="card card-pad cake-step" data-step="3" aria-labelledby="s-decorate" tabindex="-1">
                    <h2 id="s-decorate"><span class="step-no">3</span> {{ __('Decorate') }}</h2>
                    @if ($options->has('drip'))
                        <fieldset><legend>{{ __('Drip') }}</legend>
                            <div class="choice-grid">
                                <label class="choice"><input type="radio" name="drip" value="" @checked(($spec['drip'] ?? '') === '')><span>{{ __('No drip') }}</span></label>
                                @foreach ($options->get('drip') as $option)
                                    <label class="choice"><input type="radio" name="drip" value="{{ $option->code }}" data-colour="{{ $option->meta['colour'] ?? '#5a3825' }}" @checked(($spec['drip'] ?? '') === $option->code)>
                                        <span><i class="dot" style="background:{{ $option->meta['colour'] ?? '#5a3825' }}"></i> {{ $option->localized('name') }}</span><small class="tnum">{{ $price($option->price_paisa) }}</small></label>
                                @endforeach
                            </div>
                            <div class="field" id="drip-colour-field"><label for="drip_colour">{{ __('Drip colour') }}</label>
                                <input type="color" id="drip_colour" name="drip_colour" value="{{ $spec['drip_colour'] ?: '#4a2a1a' }}"></div>
                        </fieldset>
                    @endif
                    @foreach (['pattern' => [__('Side pattern'), 'pattern', __('Plain sides')], 'border_top' => [__('Top border piping'), 'border', __('No top border')], 'border_bottom' => [__('Bottom border piping'), 'border', __('No bottom border')]] as $field => [$legend, $kind, $noneLabel])
                        @if (! empty($library[$kind]))
                            <fieldset><legend>{{ $legend }}</legend>
                                <div class="choice-grid">
                                    <label class="choice"><input type="radio" name="{{ $field }}" value="" @checked(($spec[$field] ?? '') === '')><span>{{ $noneLabel }}</span></label>
                                    @foreach ($library[$kind] as $item)
                                        <label class="choice" @if ($item['shapes']) data-shapes="{{ implode(' ', $item['shapes']) }}" @endif>
                                            <input type="radio" name="{{ $field }}" value="{{ $item['code'] }}" @checked(($spec[$field] ?? '') === $item['code'])>
                                            <canvas class="style-ico" width="64" height="32" data-style-icon="{{ $kind }}:{{ $item['payload']['style'] }}" aria-hidden="true"></canvas>
                                            <span>{{ $item['name'] }}</span><small class="tnum">{{ $price($item['price_paisa']) }}@if ($item['test']) · TEST @endif</small></label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif
                    @endforeach
                    @if ($options->has('extra'))
                        <fieldset><legend>{{ __('Extras') }}</legend>
                            @foreach ($options->get('extra') as $option)
                                <label class="check"><input type="checkbox" name="extras[]" value="{{ $option->code }}" @checked(in_array($option->code, $spec['extras'] ?? [], true))> {{ $option->localized('name') }} <span class="muted tnum">{{ $price($option->price_paisa) }}</span></label>
                            @endforeach
                        </fieldset>
                    @endif
                </section>

                <section class="card card-pad cake-step" data-step="4" aria-labelledby="s-top" tabindex="-1" id="editor-root">
                    <h2 id="s-top"><span class="step-no">4</span> {{ __('Design the top') }}</h2>
                    <p class="hint">{{ __('Add your message in English or বাংলা, your own photo for an edible print, or toppers and decorations from our library. What you make here is wrapped onto the cake in the preview.') }}</p>
                    @if (! $readOnly)
                        <div class="ed-layout">
                            <div class="ed-main">
                                <div class="ed-toolbar" role="toolbar" aria-label="{{ __('Design tools') }}">
                                    <input class="input" id="ed-new-text" maxlength="60" placeholder="{{ __('Your message (English or বাংলা)') }}" aria-label="{{ __('Text to add') }}">
                                    <button type="button" class="btn btn-navy btn-sm" data-ed="add-text">{{ __('Add text') }}</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-ed="photo">{{ __('Add photo') }}</button>
                                    <input type="file" id="ed-photo" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" aria-hidden="true">
                                </div>
                                <label class="check ed-rights"><input type="checkbox" id="ed-photo-rights"> {{ __('I own the photos I upload or have permission to print them. They stay private and are only used for my cake.') }}</label>
                                <p class="field-error" id="ed-photo-error" role="alert" hidden></p>
                                <div class="ed-stage-wrap"><div id="ed-stage" class="ed-stage"></div></div>
                                <div class="ed-toolbar ed-toolbar-sub" role="toolbar" aria-label="{{ __('Edit tools') }}">
                                    <button type="button" class="btn btn-outline btn-sm" data-ed="undo">↶ {{ __('Undo') }}</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-ed="redo">↷ {{ __('Redo') }}</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-ed="center">{{ __('Centre') }}</button>
                                    <button type="button" class="btn btn-outline btn-sm" data-ed="delete">{{ __('Delete') }}</button>
                                </div>
                                <p class="hint">{{ __('Drag to move. Corner handles resize and rotate. The dashed line marks the safe print area. With the design area selected, arrow keys move the chosen item.') }}</p>
                                <ul class="ed-quality" id="ed-quality" aria-label="{{ __('Print quality') }}" hidden></ul>
                            </div>
                            <div class="ed-side">
                                <section class="ed-panel" aria-labelledby="ed-lib-h">
                                    <h3 id="ed-lib-h">{{ __('Design library') }}</h3>
                                    <input class="input" type="search" id="ed-library-search" placeholder="{{ __('Search: birthday, flower, star…') }}" aria-label="{{ __('Search the design library') }}">
                                    <div class="chip-row" role="group" aria-label="{{ __('Library sections') }}">
                                        @foreach (['' => __('All'), 'template' => __('Templates'), 'topper' => __('Toppers'), 'decoration' => __('Decorations'), 'sticker_pack' => __('Sticker packs')] as $kind => $label)
                                            <button type="button" class="chip" data-lib-kind="{{ $kind }}" aria-pressed="{{ $kind === '' ? 'true' : 'false' }}">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                    <ul class="lib-results" id="ed-library-results" aria-live="polite"></ul>
                                </section>
                                <section class="ed-panel" aria-labelledby="ed-layers-h">
                                    <h3 id="ed-layers-h">{{ __('Layers') }}</h3>
                                    <ol class="ed-layers" id="ed-layers"></ol>
                                </section>
                                <section class="ed-panel" aria-labelledby="ed-props-h">
                                    <h3 id="ed-props-h">{{ __('Selected item') }}</h3>
                                    <div id="ed-props"></div>
                                </section>
                            </div>
                        </div>
                        <div id="ed-live" class="sr-only" aria-live="polite"></div>
                    @else
                        <div class="ed-stage-wrap"><div id="ed-stage" class="ed-stage"></div></div>
                    @endif
                    <noscript><p class="small">{{ __('The top-design editor needs JavaScript. You can describe your design in the note for the baker.') }}</p></noscript>
                </section>

                @unless ($readOnly)
                    <section class="card card-pad cake-step" data-step="5" aria-labelledby="s-date" tabindex="-1">
                        <h2 id="s-date"><span class="step-no">5</span> {{ __('Date & time') }}</h2>
                        <p class="hint">{{ __('Only dates we can still bake for are shown. Standard cakes need :hours hours; tiered or complex designs need longer.', ['hours' => $leadHours]) }}</p>
                        <div class="form-grid">
                            <div class="field"><label for="date">{{ __('Date') }}</label>
                                <select class="input" id="date" name="date" data-selected="{{ $design?->scheduled_for?->format('Y-m-d') ?? old('date') }}">
                                    <option value="">{{ $dates === [] ? __('No dates available') : __('Choose a date') }}</option>
                                    @foreach ($dates as $day)
                                        <option value="{{ $day['date'] }}" data-slots='@json($day['slots'])' @selected(($design?->scheduled_for?->format('Y-m-d') ?? old('date')) === $day['date'])>{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('D j M') }}</option>
                                    @endforeach
                                </select></div>
                            <div class="field"><label for="slot">{{ __('Time slot') }}</label>
                                <select class="input" id="slot" name="slot" data-selected="{{ $design?->time_slot ?? old('slot') }}"><option value="">{{ __('Choose a date first') }}</option></select></div>
                            <div class="field full"><label for="note">{{ __('Note for the baker (optional)') }}</label>
                                <textarea class="input" id="note" name="note" rows="2" maxlength="500">{{ $spec['note'] ?? '' }}</textarea></div>
                        </div>
                        <p class="hint">{{ __('Delivery or store pickup is chosen at checkout.') }}</p>
                    </section>
                @endunless

                <section class="card card-pad cake-step" data-step="6" aria-labelledby="s-review" tabindex="-1">
                    <h2 id="s-review"><span class="step-no">6</span> {{ __('Review') }}</h2>
                    <dl class="spec-sheet" id="spec-sheet"></dl>
                    @if ($cake->ingredients || $cake->allergens)
                        <div class="allergen-box">
                            <h3>{{ __('Ingredients & allergens') }}</h3>
                            @if ($cake->ingredients)<p class="small">{{ $cake->ingredients }}</p>@endif
                            @if ($cake->allergens)<p class="small"><strong>{{ __('Contains / may contain') }}:</strong> {{ $cake->allergens }}</p>@endif
                            <p class="hint">{{ __('Our kitchen also handles nuts, gluten, dairy and eggs. We cannot guarantee allergen-free preparation.') }}</p>
                        </div>
                    @endif
                    @if ($readOnly)
                        <div class="row" style="gap:8px;flex-wrap:wrap">
                            <button class="btn btn-primary" type="submit" formaction="{{ route('cakes.shared.use', $sharedToken) }}">{{ __('Start my own copy') }}</button>
                            <button class="btn btn-outline" type="button" data-download>{{ __('Download preview') }}</button>
                        </div>
                    @else
                        <div class="form-grid">
                            <div class="field full"><label for="design-name">{{ __('Name this design (optional)') }}</label>
                                <input class="input" id="design-name" name="name" maxlength="80" value="{{ $design?->name }}" placeholder="{{ __('e.g. Ayaan’s 5th birthday') }}"></div>
                        </div>
                        <label class="check" id="rights-final" hidden><input type="checkbox" name="rights" value="1" @checked(old('rights'))> {{ __('I own the photos on this cake or have permission to print them. Our team checks every photo before printing.') }}</label>
                        @error('rights')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                        <div class="review-actions">
                            <button class="btn btn-primary" type="submit" @disabled(! $ordersOpen)><x-icon name="cart" /> {{ $design && $design->status === \App\Models\CakeDesign::IN_CART ? __('Update cake in cart') : __('Add cake to cart') }}</button>
                            @auth
                                <button class="btn btn-outline" type="submit" formaction="{{ route('cakes.save') }}" data-save>{{ __('Save design') }}</button>
                            @else
                                <a class="btn btn-outline" href="{{ route('login') }}">{{ __('Log in to save designs') }}</a>
                            @endauth
                            @if ($design)
                                <button class="btn btn-ghost" type="button" data-share="{{ route('cakes.share', $design->uuid) }}">{{ __('Share link') }}</button>
                            @endif
                            <button class="btn btn-ghost" type="button" data-download>{{ __('Download preview') }}</button>
                        </div>
                        <p class="small" id="share-result" role="status" hidden></p>
                    @endif
                </section>

                <div class="step-nav" hidden>
                    <button type="button" class="btn btn-outline" data-step-prev>← {{ __('Back') }}</button>
                    <button type="button" class="btn btn-navy" data-step-next>{{ __('Next') }} →</button>
                </div>
            </div>


            <div class="cake-mobile-bar" hidden>
                <span><small>{{ __('Cake price') }}</small><strong class="tnum" id="cake-total-mobile">{{ Taka::format($priced['line_paisa'] ?? 0) }}</strong></span>
                <button type="button" class="btn btn-navy" data-step-next>{{ __('Next') }} →</button>
                @unless ($readOnly)
                    <button type="submit" class="btn btn-primary" data-bar-submit hidden @disabled(! $ordersOpen)><x-icon name="cart" /> {{ __('Add cake to cart') }}</button>
                @endunless
            </div>
        </form>
        <script type="module" src="{{ Asset::url('js/cake-designer.js') }}"></script>
    @endif
@endsection
