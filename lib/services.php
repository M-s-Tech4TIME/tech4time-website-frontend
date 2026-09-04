<?php
/**
 * Tech4TIME — services data access and rendering.
 *
 * Reading the file is lib/store.php; escaping is lib/html.php; the SHAPE of
 * the pages is lib/contract.php, which the frontend and the backend hold
 * byte-identical. What is left here is this side's own business with that
 * shape — and on this side that is the whole of the drawing.
 *
 * ONE DOCUMENT, SEVEN PAGES. Unlike every other renderer in lib/, this file
 * draws more than one page: the services index and each of the service detail
 * pages beneath it. They are one document because a service has to be
 * addable from the editor and CONTRACT_DOCUMENTS is a constant in code — see
 * the note over services_defaults() in contract.php. Keeping the drawing in
 * one file follows from that: the six detail pages are one template with six
 * sets of words, and they share a stylesheet with no per-service rule in it.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":  set on every save
 *     "revision": monotonic; see contract.php
 *     "meta":     { title, description, share_title }
 *     "hero":     { title, subtitle }
 *     "nav":      { status, eyebrow, title, lead,
 *                   items[ { id, service, icon, title, text, status } ] }
 *     "blocks":   { status,
 *                   items[ { id, service, icon, title, intro, status,
 *                            groups[ { id, title, width, items[], status } ],
 *                            buttons[ { id, label, href, icon, style,
 *                                       status } ] } ] }
 *     "ossf":     { status, eyebrow, title, lead,
 *                   items[ { id, icon, title, text, status } ] }
 *     "cta":      { status, title, text, label, href, icon }
 *     "services": { items[ <one whole detail page> ] }
 *   }
 *
 * and one detail page is
 *
 *   { id, slug, name, status,
 *     meta   { title, description, share_title },
 *     hero   { title, subtitle },
 *     core   { status, eyebrow, title, lead,
 *              note{ text, link_label, link_href },
 *              items[ { id, icon, title, text, status } ] },
 *     layers { status, eyebrow, title, lead,
 *              labels{ purpose, features, tags, count_one, count_many },
 *              items[ { id, icon, title, tab_text, text, hub_label, status,
 *                       cards[ { id, icon, name, category, desc, purpose,
 *                                features[], tags[], status } ] } ] },
 *     cta    { status, title, text, label, href, icon } }
 *
 * EVERY FIELD HERE IS PLAIN TEXT. The services pages have no rich-text field
 * at all — see the services branch of contract_sanitise() for why. Everything
 * this file prints goes through h().
 *
 * THREE THINGS ARE DRAWN AND NOT STORED, and this is deliberate:
 *   - the detail card beside each ring is that layer's first card, redrawn;
 *   - every node on the ring is a projection of a card — its id, its icon and
 *     its name, for the screen reader;
 *   - "12 Solutions" under a layer heading is the card count.
 * Storing any of them would let them fall out of step with the cards they
 * describe, which is exactly what they are for.
 *
 * Icons used by this file, for tools/inject_icons.py, which finds symbols by
 * scanning for a literal href="#name" and cannot see one built at run time.
 * The model's own list is SERVICES_ICONS in lib/contract.php; these two are
 * the furniture this file draws itself:
 *   #check #arrow-right
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';

const SERVICES_FILE = __DIR__ . '/../content/services.json';

/**
 * The two symbols this file draws for itself.
 *
 * The tick beside a feature and the chevron on a button are furniture: they
 * are not a choice anybody makes, so they are not in SERVICES_ICONS and the
 * editor never offers them. services_icon() still has to be allowed to draw
 * them, which is what this is for.
 */
const SERVICES_FURNITURE = ['check', 'arrow-right'];

/**
 * How wide a ring is drawn, by how many nodes are on it.
 *
 * The stylesheet defines .soc-map--sm, the unmodified default and
 * .soc-map--lg; which one a ring takes follows from its node count, because
 * the count is what decides whether the nodes crowd. The two thresholds are
 * CHOSEN rather than measured: the shipped pages only ever have 3, 4, 5, 6, 12
 * or 21 nodes, so any boundary between 6 and 12, and between 12 and 21, would
 * reproduce them. These are the round numbers in those gaps.
 */
const SERVICES_RING_SMALL = 6;
const SERVICES_RING_LARGE = 15;

/**
 * The largest ring the stylesheet can draw.
 *
 * .soc-map--n2 through .soc-map--n24 each set the --n custom property that
 * places the nodes. A twenty-fifth card would emit .soc-map--n25, which no
 * rule matches, leaving --n unset and every node stacked at the centre. The
 * class is clamped instead: the ring stops spreading, and the grid below --
 * which is what actually carries the cards -- is unaffected.
 */
const SERVICES_RING_MAX = 24;

/**
 * The services document as it should be rendered.
 *
 * Never throws. A missing, unreadable or damaged file falls back field by
 * field to what services_defaults() ships with, so the pages are stale at
 * worst and never blank.
 */
function services_load(): array
{
    return services_normalise(store_read(SERVICES_FILE) ?? []);
}

/* ------------------------------------------------------------- small parts */

/**
 * One <svg><use> reference.
 *
 * The name is checked against the sprite the model offers -- plus SERVICES_FURNITURE,
 * the two this file draws for itself and which are deliberately not offered as
 * a choice -- rather than printed blind: an icon field carrying something the sprite has no symbol for would
 * otherwise render an empty box on a live page. An unknown name draws nothing
 * at all, which is a card without a picture rather than a hole with a border.
 */
function services_icon(string $name, string $class = 'icon'): string
{
    $name = trim($name);
    if ($name === '' || !(isset(SERVICES_ICONS[$name]) || in_array($name, SERVICES_FURNITURE, true))) {
        return '';
    }

    return '<svg class="' . h($class) . '" aria-hidden="true" focusable="false">'
        . '<use href="#' . h($name) . '"></use></svg>';
}

/**
 * A button, with its icon on the side the icon belongs on.
 *
 * DERIVED, and the rule is the whole of it: the chevron goes AFTER the label,
 * every other icon goes before. It holds for all nine buttons and links across
 * the seven pages -- the two closing buttons carry a calendar and lead with
 * it, the "Explore" buttons carry a chevron and trail it, and the one ghost
 * button carries a certificate and leads with that. It is not a coincidence
 * either: arrow-right is a direction, and a direction reads after the thing it
 * points away from.
 */
function services_button(string $label, string $href, string $icon,
                         string $class, string $indent, string $attrs = ''): string
{
    $svg = services_icon($icon, 'icon icon--sm');
    $pad = $indent . '  ';

    $out = $indent . '<a' . $attrs . ' class="' . h($class) . '" href="' . h($href) . "\">\n";
    if ($svg !== '' && $icon !== 'arrow-right') {
        $out .= $pad . $svg . "\n";
    }
    $out .= $pad . h($label) . "\n";
    if ($svg !== '' && $icon === 'arrow-right') {
        $out .= $pad . $svg . "\n";
    }

    return $out . $indent . "</a>\n";
}

/** The eyebrow / title / lead block a band opens with. */
function services_header(array $band, string $id): string
{
    $out = '        <div data-reveal data-reveal-delay class="section__header">' . "\n";

    if (trim((string)$band['eyebrow']) !== '') {
        $out .= '          <span class="section__eyebrow">' . h($band['eyebrow']) . "</span>\n";
    }
    $out .= '          <h2 class="section__title" id="' . h($id) . '">'
          . h($band['title']) . "</h2>\n";
    if (trim((string)$band['lead']) !== '') {
        $out .= '          <p class="section__lead">' . h($band['lead']) . "</p>\n";
    }

    return $out . "        </div>\n";
}

/**
 * The closing band, on the index and on all six detail pages.
 *
 * The icon comes before the label, which is the order the button was written
 * in on all seven pages.
 */
function services_cta(array $cta, string $id, string $class = 'cta-band'): string
{
    if (($cta['status'] ?? 'shown') === 'hidden') {
        return '';
    }

    $out  = '  <section class="' . h($class) . '" aria-labelledby="' . h($id) . "\">\n";
    $out .= "    <div class=\"container cta-band__inner\">\n";
    $out .= '      <h2 data-reveal data-reveal-delay class="cta-band__title" id="' . h($id) . '">'
          . h($cta['title']) . "</h2>\n";
    $out .= '      <p data-reveal data-reveal-delay class="cta-band__text">'
          . h($cta['text']) . "</p>\n";
    $out .= services_button($cta['label'], $cta['href'], $cta['icon'],
                            'btn btn--primary btn--lg', '      ',
                            ' data-reveal data-reveal-delay');
    $out .= "    </div>\n";

    return $out . "  </section>\n";
}

/* ------------------------------------------------------ the six detail pages */

/** One of the two cards at the top of a detail page. */
function services_core_card(array $row): string
{
    $out  = '        <article data-reveal data-reveal-delay class="core-card">' . "\n";
    $out .= '          <span class="core-card__icon">' . services_icon($row['icon']) . "</span>\n";
    $out .= '          <h3 class="core-card__title">' . h($row['title']) . "</h3>\n";
    $out .= '          <p class="core-card__text">' . h($row['text']) . "</p>\n";

    return $out . "        </article>\n";
}

/** The core-solutions band of a detail page. */
function services_core_band(array $service): string
{
    $core = $service['core'];
    if (($core['status'] ?? 'shown') === 'hidden') {
        return '';
    }

    $out  = '  <section class="section core-solutions" aria-labelledby="core-heading">' . "\n";
    $out .= "    <div class=\"container\">\n";
    $out .= services_header($core, 'core-heading');

    $cards = services_rows_shown($core['items']);
    if ($cards) {
        $out .= "      <div class=\"core-solutions__grid\">\n";
        foreach ($cards as $row) {
            $out .= services_core_card($row);
        }
        $out .= "      </div>\n";
    }

    $note = $core['note'];
    if (trim((string)$note['text']) !== '') {
        $out .= "      <p data-reveal data-reveal-delay class=\"core-solutions__note\">\n";
        $out .= '        ' . h($note['text']) . "\n";
        if (trim((string)$note['link_label']) !== '') {
            $out .= '        <a href="' . h($note['link_href']) . '">' . h($note['link_label'])
                  . services_icon('arrow-right', 'icon icon--sm') . "</a>\n";
        }
        $out .= "      </p>\n";
    }

    $out .= "    </div>\n";

    return $out . "  </section>\n";
}

/**
 * One solution card.
 *
 * $detail draws the copy that sits beside the ring: same card, one extra
 * class, and no id -- the id belongs to the card in the grid below, which is
 * what a fragment link and a ring node both point at. Two ids for one card
 * would make the anchor ambiguous and the ring's scroll land in the wrong
 * place.
 */
function services_card(array $card, array $labels, bool $detail = false): string
{
    $cls = $detail ? 'tool-card tool-card--detail' : 'tool-card';
    $id  = $detail ? '' : ' id="' . h($card['id']) . '"';

    $out  = '          <article class="' . $cls . '"' . $id . ">\n";
    $out .= "            <div class=\"tool-card__head\">\n";
    $out .= '              <span class="tool-card__icon">' . services_icon($card['icon']) . "</span>\n";
    $out .= "              <div class=\"tool-card__titles\">\n";
    $out .= '                <h4 class="tool-card__name">' . h($card['name']) . "</h4>\n";
    $out .= '                <p class="tool-card__category">' . h($card['category']) . "</p>\n";
    $out .= "              </div>\n";
    $out .= "            </div>\n";
    $out .= '            <p class="tool-card__desc">' . h($card['desc']) . "</p>\n";
    $out .= "            <p class=\"tool-card__purpose\">\n";
    $out .= '              <span class="tool-card__label">' . h($labels['purpose']) . "</span>\n";
    $out .= '              ' . h($card['purpose']) . "\n";
    $out .= "            </p>\n";

    if ($card['features'] && trim((string)$labels['features']) !== '') {
        $out .= "            <div class=\"tool-card__block\">\n";
        $out .= '              <span class="tool-card__label">' . h($labels['features']) . "</span>\n";
        $out .= "              <ul class=\"tool-card__features\" role=\"list\">\n";
        foreach ($card['features'] as $feature) {
            $out .= '                <li class="tool-card__feature">'
                  . services_icon('check', 'icon icon--sm tool-card__check')
                  . h($feature) . "</li>\n";
        }
        $out .= "              </ul>\n";
        $out .= "            </div>\n";
    }

    if ($card['tags'] && trim((string)$labels['tags']) !== '') {
        $out .= "            <div class=\"tool-card__toolset\">\n";
        $out .= '              <span class="tool-card__label">' . h($labels['tags']) . "</span>\n";
        $out .= "              <ul class=\"tool-card__tags\" role=\"list\">\n";
        foreach ($card['tags'] as $tag) {
            $out .= '                <li class="tag">' . h($tag) . "</li>\n";
        }
        $out .= "              </ul>\n";
        $out .= "            </div>\n";
    }

    return $out . "          </article>\n";
}

/**
 * One layer panel: the ring, the card beside it, and the grid under both.
 *
 * The ring, the card beside it and the count are all drawn from $cards and
 * never stored -- see the note at the top of this file.
 */
function services_layer(array $layer, array $labels): string
{
    $cards = services_rows_shown($layer['cards']);
    $n     = count($cards);

    $out  = '      <section class="layer tabs__panel" id="'
          . h(SERVICES_LAYER_PREFIX . $layer['id']) . "\">\n";
    $out .= "        <div class=\"layer__header\">\n";
    $out .= "          <div>\n";
    $out .= "            <h3 class=\"layer__title\">\n";
    $out .= '              <span class="layer__icon">' . services_icon($layer['icon']) . "</span>\n";
    $out .= '              ' . h($layer['title']) . "\n";
    $out .= "            </h3>\n";
    $out .= '            <p class="layer__text">' . h($layer['text']) . "</p>\n";
    $out .= "          </div>\n";
    $out .= '          <p class="layer__count">' . $n . ' '
          . h($n === 1 ? $labels['count_one'] : $labels['count_many']) . "</p>\n";
    $out .= "        </div>\n";

    if ($n > 0) {
        /* Nodes are buttons, so pointer, keyboard and touch all reach them;
           each one brings its card up beside the ring. Hidden under 52em,
           where the ring is illegible and the grid below carries everything. */
        $out .= "        <div class=\"layer__viz\" data-solution-map>\n";

        $size = $n <= SERVICES_RING_SMALL ? ' soc-map--sm'
              : ($n >= SERVICES_RING_LARGE ? ' soc-map--lg' : '');
        $out .= '          <ul class="soc-map soc-map--n' . min($n, SERVICES_RING_MAX)
              . $size . "\">\n";
        $out .= "            <li class=\"soc-map__hub\">\n";
        $out .= '              ' . services_icon($layer['icon']) . "\n";
        $out .= '              <span class="soc-map__hub-label">' . h($layer['hub_label']) . "</span>\n";
        $out .= "            </li>\n";
        foreach ($cards as $card) {
            $out .= "            <li class=\"soc-map__slot\">\n";
            $out .= '              <button class="soc-map__node" type="button" data-solution="'
                  . h($card['id']) . "\">\n";
            $out .= '                ' . services_icon($card['icon']) . "\n";
            $out .= '                <span class="visually-hidden">' . h($card['name']) . "</span>\n";
            $out .= "              </button>\n";
            $out .= "            </li>\n";
        }
        $out .= "          </ul>\n";
        $out .= "          <div class=\"layer__detail\" data-solution-detail>\n";
        $out .= services_card($cards[0], $labels, true);
        $out .= "          </div>\n";
        $out .= "        </div>\n";

        $out .= "        <div class=\"layer__grid\">\n";
        foreach ($cards as $card) {
            $out .= services_card($card, $labels);
        }
        $out .= "        </div>\n";
    }

    return $out . "      </section>\n";
}

/** The tabbed layers band of a detail page. */
function services_layers_band(array $service): string
{
    $band = $service['layers'];
    if (($band['status'] ?? 'shown') === 'hidden') {
        return '';
    }

    $layers = services_rows_shown($band['items']);
    $labels = $band['labels'];

    $out  = '  <section class="section section--surface layers" aria-labelledby="layers-heading">' . "\n";
    $out .= "    <div class=\"container\">\n";
    $out .= services_header($band, 'layers-heading');

    if ($layers) {
        $out .= "      <div class=\"tabs tabs--cards\" data-tabs>\n";
        $out .= "        <div class=\"tabs__list\" data-tabs-list>\n";
        foreach ($layers as $layer) {
            $out .= '          <a data-reveal data-reveal-delay class="tabs__tab" href="#'
                  . h(SERVICES_LAYER_PREFIX . $layer['id']) . "\" data-tabs-tab>\n";
            $out .= '            <span class="tabs__tab-icon">' . services_icon($layer['icon']) . "</span>\n";
            $out .= "            <span class=\"tabs__tab-body\">\n";
            $out .= '              <span class="tabs__tab-name">' . h($layer['title']) . "</span>\n";
            $out .= '              <span class="tabs__tab-text">' . h($layer['tab_text']) . "</span>\n";
            $out .= "            </span>\n";
            $out .= "          </a>\n";
        }
        $out .= "        </div>\n";

        /* The panels live INSIDE the tabs wrapper, after the list. That is
           what tabs.js binds to, and it is why every layer is on the page with
           scripting off: the tabs choose which one is in view, they do not
           fetch it. */
        foreach ($layers as $layer) {
            $out .= services_layer($layer, $labels);
        }

        $out .= "      </div>\n";
    }

    $out .= "    </div>\n";

    return $out . "  </section>\n";
}

/* ------------------------------------------------------------- the index */

/** The six cards that jump down the page to a service block. */
function services_nav_band(array $data): string
{
    if (!services_band_shown($data, 'nav')) {
        return '';
    }

    $out  = '  <section class="section section--tight service-nav" aria-labelledby="service-nav-heading">' . "\n";
    $out .= "    <div class=\"container\">\n";
    $out .= services_header($data['nav'], 'service-nav-heading');
    $out .= "      <div class=\"service-nav__grid\">\n";

    foreach (services_nav_visible($data) as $row) {
        $out .= '        <a data-reveal data-reveal-delay class="service-nav-card" href="#'
              . h($row['block']) . "\">\n";
        $out .= "          <span class=\"service-nav-card__icon\">\n";
        $out .= '            ' . services_icon($row['icon']) . "\n";
        $out .= "          </span>\n";
        $out .= "          <span class=\"service-nav-card__body\">\n";
        $out .= '            <span class="service-nav-card__title">' . h($row['title']) . "</span>\n";
        $out .= '            <span class="service-nav-card__text">' . h($row['text']) . "</span>\n";
        $out .= "          </span>\n";
        $out .= '          ' . services_icon('arrow-right', 'icon icon--sm service-nav-card__arrow') . "\n";
        $out .= "        </a>\n";
    }

    $out .= "      </div>\n";
    $out .= "    </div>\n";

    return $out . "  </section>\n";
}

/**
 * The six service blocks.
 *
 * The alternating tint is a position, not a field: the first, third and fifth
 * block that a visitor can SEE carry section--surface. Deriving it from the
 * shown rows rather than from the stored index is what keeps the stripe
 * correct when a service is hidden -- storing it would leave two plain blocks
 * side by side the moment one between them was switched off.
 */
function services_blocks_band(array $data): string
{
    if (!services_band_shown($data, 'blocks')) {
        return '';
    }

    $out = '';
    foreach (services_blocks_visible($data) as $i => $block) {
        $cls = $i % 2 === 0 ? 'service-block section--surface' : 'service-block';

        $out .= '  <section class="' . $cls . '" id="' . h($block['id'])
              . '" aria-labelledby="' . h($block['id']) . "-heading\">\n";
        $out .= "    <div class=\"container\">\n";
        $out .= "      <header data-reveal data-reveal-delay class=\"service-block__header\">\n";
        $out .= "        <div class=\"service-block__heading\">\n";
        $out .= "          <span class=\"service-block__icon\">\n";
        $out .= '            ' . services_icon($block['icon']) . "\n";
        $out .= "          </span>\n";
        $out .= '          <h2 class="service-block__title" id="' . h($block['id']) . '-heading">'
              . h($block['title']) . "</h2>\n";
        $out .= "        </div>\n";
        $out .= "        <div class=\"service-block__rule\" aria-hidden=\"true\"></div>\n";
        $out .= '        <p class="service-block__intro">' . h($block['intro']) . "</p>\n";
        $out .= "      </header>\n";

        $groups = services_rows_shown($block['groups']);
        if ($groups) {
            /* --pair means what the name says: the groups that do NOT span
               the row form a pair, and the grid under the wide one is two
               columns rather than however many there happen to be. It follows
               from the count and is not stored -- the cybersecurity block has
               a wide group and exactly two beside it and carries the class;
               the HRaaS block has a wide group and nothing beside it and does
               not. */
            $narrow = 0;
            foreach ($groups as $group) {
                if ($group['width'] !== 'wide') {
                    $narrow++;
                }
            }
            $pair = $narrow === 2 ? ' service-block__groups--pair' : '';
            $out .= '      <div class="service-block__groups' . $pair . "\">\n";

            foreach ($groups as $group) {
                $wide = $group['width'] === 'wide';
                $out .= '        <article data-reveal data-reveal-delay class="service-group'
                      . ($wide ? ' service-group--wide' : '') . "\">\n";
                $out .= '          <h3 class="service-group__title">' . h($group['title']) . "</h3>\n";
                $out .= '          <ul class="service-group__list'
                      . ($wide ? ' service-group__list--split' : '') . "\" role=\"list\">\n";
                foreach ($group['items'] as $item) {
                    $out .= "            <li class=\"service-group__item\">\n";
                    $out .= '              '
                          . services_icon('check', 'icon icon--sm service-group__check') . "\n";
                    $out .= '              ' . h($item) . "\n";
                    $out .= "            </li>\n";
                }
                $out .= "          </ul>\n";
                $out .= "        </article>\n";
            }
            $out .= "      </div>\n";
        }

        $buttons = services_rows_shown($block['buttons']);
        if ($buttons) {
            $out .= "      <p data-reveal data-reveal-delay class=\"service-block__footer\">\n";
            foreach ($buttons as $button) {
                $out .= services_button($button['label'], $button['href'], $button['icon'],
                                        'btn btn--' . $button['style'], '        ');
            }
            $out .= "      </p>\n";
        }

        $out .= "    </div>\n";
        $out .= "  </section>\n";
    }

    return $out;
}

/** The eight stages of the OSSF framework. The number beside one is its place. */
function services_ossf_band(array $data): string
{
    if (!services_band_shown($data, 'ossf')) {
        return '';
    }

    $out  = '  <section class="section section--surface ossf" id="ossf-framework" aria-labelledby="ossf-heading">' . "\n";
    $out .= "    <div class=\"container\">\n";
    $out .= services_header($data['ossf'], 'ossf-heading');
    $out .= "      <ol class=\"ossf__grid\" role=\"list\">\n";

    foreach (services_shown($data, 'ossf') as $i => $row) {
        $out .= "        <li data-reveal data-reveal-delay class=\"ossf-card\">\n";
        $out .= '          <span class="ossf-card__step" aria-hidden="true">'
              . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . "</span>\n";
        $out .= "          <span class=\"ossf-card__icon\">\n";
        $out .= '            ' . services_icon($row['icon']) . "\n";
        $out .= "          </span>\n";
        $out .= '          <h3 class="ossf-card__title">' . h($row['title']) . "</h3>\n";
        $out .= '          <p class="ossf-card__text">' . h($row['text']) . "</p>\n";
        $out .= "        </li>\n";
    }

    $out .= "      </ol>\n";
    $out .= "    </div>\n";

    return $out . "  </section>\n";
}

/* --------------------------------------------------------- structured data */

/**
 * The Service block for one detail page.
 *
 * GENERATED, for the reason home_service_schema() is. The offer catalogue is
 * the page's layers -- it was literal JSON maintained beside them, and the two
 * had already drifted once: the software development page's catalogue read
 * "Quality Assurance & Testing" where its own tab read "Quality Assurance
 * &amp; Testing", an escaping fault that the schema had escaped and the markup
 * had not. A layer added is now a catalogue entry by being a layer, and a
 * hidden one is absent from both.
 *
 * The name of the catalogue follows the service, which is how all six were
 * written. serviceType does not: it is schema.org's word for the practice, and
 * on two of the six it differs from what the page calls itself.
 */
function services_schema(array $service, string $origin): array
{
    $url  = rtrim($origin, '/') . '/pages/services/' . $service['slug'] . '/';
    $name = $service['name'];

    /* A HIDDEN BAND HAS NO CATALOGUE. The offer catalogue is the layers, and
       the layers are not on the page when the band is switched off -- so
       listing them here would tell a crawler about content a reader cannot
       find, and every URL in it would be an anchor to nothing. Hiding has to
       mean the same thing to both audiences. */
    $catalog = [];
    $layers  = ($service['layers']['status'] ?? 'shown') === 'hidden'
        ? [] : services_rows_shown($service['layers']['items']);

    foreach ($layers as $layer) {
        $catalog[] = [
            '@type' => 'OfferCatalog',
            'name'  => $layer['title'],
            'url'   => $url . '#' . SERVICES_LAYER_PREFIX . $layer['id'],
        ];
    }

    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'Service',
        'name'        => $name,
        'serviceType' => $service['schema_type'] !== '' ? $service['schema_type'] : $name,
        'url'         => $url,
        'provider'    => [
            '@type' => 'Organization',
            'name'  => 'Tech4TIME',
            'url'   => rtrim($origin, '/') . '/',
        ],
        'areaServed'  => ['BD', 'MY', 'BE'],
        'description' => $service['schema_description'],
        'hasOfferCatalog' => [
            '@type'           => 'OfferCatalog',
            'name'            => $name . ' Solutions',
            'itemListElement' => $catalog,
        ],
    ];
}

/**
 * The BreadcrumbList for one detail page.
 *
 * Generated for the same reason: it names the service, and a renamed service
 * whose trail still said the old name would be telling a crawler one thing and
 * a reader another.
 */
function services_breadcrumbs(array $service, string $origin): array
{
    $origin = rtrim($origin, '/');

    return [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home',
             'item' => $origin . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services',
             'item' => $origin . '/pages/services/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $service['name'],
             'item' => $origin . '/pages/services/' . $service['slug'] . '/'],
        ],
    ];
}

/**
 * JSON-LD, printed the way the pages already carry it.
 *
 * JSON_UNESCAPED_SLASHES and JSON_UNESCAPED_UNICODE because every URL in these
 * blocks would otherwise arrive full of backslashes -- that pair is what the
 * home and about pages already use.
 *
 * JSON_HEX_TAG IS ADDED TO IT, and is the one deliberate difference. A stored
 * value holding "</script>" would otherwise close the block it sits inside,
 * and every string in these blocks is editable from the admin. It costs
 * nothing today: no value in the document contains a < or a > , so the output
 * is byte for byte what the six pages already carried. The home and about
 * pages take the same input and do not set it; that is worth fixing there too,
 * and is not this change's business.
 */
function services_json_ld(array $data): string
{
    return (string)json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG
    );
}

/* ------------------------------------------------------------- the sprite */

/**
 * Every icon this page will actually draw, in sprite order.
 *
 * Pass a service for a detail page, or null for the index.
 *
 * WHY THIS IS NOT tools/inject_icons.py's JOB. That tool finds the symbols a
 * page needs by scanning its source for a literal href="#name", and inlines
 * exactly those. It cannot see a name that comes out of content/services.json
 * at run time, so a page like this one has to either name every icon the model
 * offers in a comment -- the about page's bargain -- or work it out here.
 *
 * The about page names all sixteen and the cost is nothing. This model offers
 * SEVENTY-SIX, and naming them all measured +7 to +10 KB GZIPPED on every one
 * of the seven pages, 24% to 44% more than each shipped before. That is a real
 * regression on a site whose page weight was measured this week, so the page
 * works out its own set instead: it is the only thing that knows.
 *
 * It is also the only version that cannot go stale. An icon chosen in the
 * admin arrives in the same file as the card that chose it, so the symbol is
 * inlined by the same publish that made it necessary -- where a scanned list
 * would need a deploy to catch up, and would draw an empty box until it did.
 */
function services_icons_used(array $data, ?array $service): array
{
    /* The furniture is added only where it is actually drawn. The tick
       belongs to a ticked list, and three of the six detail pages have none;
       the chevron belongs to the nav cards and the note link. Adding both
       unconditionally cost about 150 bytes on a page that drew neither. */
    $names = [];

    if ($service === null) {
        foreach (services_nav_visible($data) as $row) {
            $names[] = $row['icon'];
            $names[] = 'arrow-right';
        }
        foreach (services_blocks_visible($data) as $block) {
            foreach (services_rows_shown($block['groups']) as $group) {
                if ($group['items']) {
                    $names[] = 'check';
                    break 2;
                }
            }
        }
        foreach (services_shown($data, 'ossf') as $row) {
            $names[] = $row['icon'];
        }
        foreach (services_blocks_visible($data) as $block) {
            $names[] = $block['icon'];
            foreach (services_rows_shown($block['buttons']) as $button) {
                $names[] = $button['icon'];
            }
        }
        $names[] = $data['cta']['icon'];
    } else {
        foreach (services_rows_shown($service['core']['items']) as $row) {
            $names[] = $row['icon'];
        }
        if (trim((string)$service['core']['note']['link_label']) !== '') {
            $names[] = 'arrow-right';
        }
        if (trim((string)$service['layers']['labels']['features']) !== '') {
            $names[] = 'check';
        }
        foreach (services_rows_shown($service['layers']['items']) as $layer) {
            $names[] = $layer['icon'];
            foreach (services_rows_shown($layer['cards']) as $card) {
                $names[] = $card['icon'];
            }
        }
        $names[] = $service['cta']['icon'];
    }

    $names = array_filter(array_unique($names), static fn($n): bool => trim((string)$n) !== '');

    return array_values($names);
}

/**
 * The inline sprite, holding those symbols and no others.
 *
 * A SECOND sprite, sitting after the one tools/inject_icons.py maintains.
 * That tool still owns the chrome -- the header, the dock, the footer, the
 * theme toggle -- because those symbols ARE in the page's source, where it can
 * see them, and the set differs from page to page. This block owns only what
 * comes out of the document. Splitting them that way is what keeps the tool
 * working unchanged and keeps this function from having to know what a footer
 * contains.
 *
 * Its markers are deliberately NOT icon-sprite:start/end. Those delimit the
 * block inject_icons.py rewrites, and a second pair would make its non-greedy
 * match end in the wrong place.
 *
 * Two <symbol> elements with one id can overlap -- three do today, where a
 * card and the dock both use #cogs. The first definition wins and the two are
 * identical markup, so the page renders the same; it costs a few hundred bytes
 * to not have to tell this function what the chrome uses.
 *
 * A name with no symbol behind it is skipped rather than fatal: services_icon()
 * already refuses to draw an icon the model does not offer, and a sprite that
 * threw would take a whole page down over one bad row.
 */
function services_sprite(array $names): string
{
    static $symbols = null;

    if ($symbols === null) {
        $symbols = [];
        $svg = @file_get_contents(__DIR__ . '/../assets/icons/sprite.svg');
        if ($svg !== false) {
            preg_match_all('/<symbol id="([^"]+)".*?<\/symbol>/s', $svg, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                $symbols[$hit[1]] = $hit[0];
            }
        }
    }

    /* Sprite order, not page order: the file is the canonical sequence, and a
       block that reordered itself with the content would churn every diff. */
    $body = '';
    foreach ($symbols as $id => $markup) {
        if (in_array($id, $names, true)) {
            $body .= '  ' . $markup . "\n";
        }
    }

    return "<!-- content-sprite:start -->\n"
        . '<svg class="icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . "\n"
        . $body
        . "</svg>\n"
        . '<!-- content-sprite:end -->';
}
