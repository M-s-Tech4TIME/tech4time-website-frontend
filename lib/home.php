<?php
/**
 * Tech4TIME — home page data access.
 *
 * Reading the file is lib/store.php; escaping and rich-text sanitising is
 * lib/html.php; the SHAPE of the page is lib/contract.php, which the frontend
 * and the backend hold byte-identical. What is left here is this side's own
 * business with that shape.
 *
 * On THIS side that is: the Service ItemList structured data, the <picture> a
 * destination card turns into, the accent span inside the hero title, and the
 * lines of the hero terminal. Validation, the uploads and saving are the
 * backend's — nothing here writes the home page. The only thing on this host
 * that writes content at all is api/publish.php, landing a document the
 * backend signed.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":      set on every save
 *     "revision":     monotonic; see contract.php
 *     "meta":         { title, description, share_title }
 *     "hero":         { title, accent, cta_label, cta_href }
 *     "badges":       { status, items[ { id, icon, label, status } ] }
 *     "tags":         { status, items[ { id, icon, label, status } ] }
 *     "terminal":     { status, title, summary,
 *                       items[ { id, kind, tone, prompt, text, status } ] }
 *     "capabilities": { status, title, lead,
 *                       items[ { id, icon, title, status } ] }
 *     "services":     { status, eyebrow, title, lead, schema_name,
 *                       schema_description,
 *                       items[ { id, icon, title, text, href, label,
 *                                link_hint, status } ] }
 *     "destinations": { status, eyebrow, title, lead,
 *                       items[ { id, title, text, href, label, link_hint,
 *                                alt, image{}, image_dark{}, status } ] }
 *     "cta":          { status, icon, title, text, label, href }
 *   }
 *
 * and an image{} is { src, webp, width, height } — see contract_image_defaults().
 *
 * EVERY FIELD HERE IS PLAIN TEXT. The home page has no rich-text field at all
 * — see HOME_ROW_RICH_FIELDS in contract.php for why. The three functions
 * below that return markup build it from escaped values themselves; nothing on
 * this page prints a stored string unescaped.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';

const HOME_FILE = __DIR__ . '/../content/home.json';

/**
 * The home page as it should be rendered.
 *
 * Never throws. A missing, unreadable or damaged file falls back field by
 * field to what home_defaults() ships with, so the site's front door is stale
 * at worst and never blank.
 */
function home_load(): array
{
    return home_normalise(store_read(HOME_FILE) ?? []);
}

/* ---------------------------------------------------------------- the hero */

/**
 * The <h1>, with one phrase in the accent colour.
 *
 * The title is stored as plain text and the phrase to emphasise is stored
 * beside it, rather than the heading being stored as markup. That keeps the
 * one field an operator types free of HTML, and keeps the class name — which
 * belongs to the stylesheet — out of the content.
 *
 * The split is made on the RAW strings and each part escaped afterwards, not
 * the other way round: searching escaped text for an escaped needle works
 * until the accent contains an "&", at which point the needle is "&amp;" in
 * one string and "&" in the other and the match silently stops happening.
 *
 * A phrase that does not appear in the title — a typo, or a title edited
 * without the accent being updated — renders the title plain. It is a heading
 * losing its colour, not a page losing its heading, and the editor says so
 * before it is saved.
 */
function home_hero_title(string $title, string $accent): string
{
    $accent = trim($accent);

    if ($accent === '') {
        return h($title);
    }

    /* strpos and substr, NOT mb_*. This host has mbstring and the next one may
       not; lib/html.php and the backend's lib/admin.php make the same choice
       for the same reason. Bytes are safe for exactly this operation because
       UTF-8 is self-synchronising: a byte-level match of a valid UTF-8 needle
       inside a valid UTF-8 haystack can only begin and end on a character
       boundary, so neither cut can land mid-character. */
    $at = strpos($title, $accent);
    if ($at === false) {
        return h($title);
    }

    return h(substr($title, 0, $at))
        . '<span class="hero__accent">' . h($accent) . '</span>'
        . h(substr($title, $at + strlen($accent)));
}

/* ------------------------------------------------------------ the terminal */

/**
 * The lines of the hero terminal, as markup.
 *
 * TWO THINGS THIS FUNCTION IS CAREFUL ABOUT.
 *
 * 1. NO INDENTATION. .terminal__line is white-space: pre-wrap, so that an
 *    overlong line wraps back to column 0 the way a shell does. pre-wrap also
 *    renders the HTML source's own indentation as leading spaces, so a line
 *    emitted with the surrounding markup's indentation appears indented on the
 *    page. The literal markup this replaced carried a comment saying exactly
 *    this; the loop has to honour it, and each line therefore begins at column
 *    0 and ends with its own newline.
 *
 * 2. THE CARET IS NOT A ROW. The blinking cursor at the end of the session is
 *    emitted here, after the last line, and is not in the document. An
 *    operator cannot delete it, cannot end up with two, and cannot leave it
 *    stranded in the middle — all three of which are reachable if it is just
 *    another row somebody can reorder. Its prompt follows the last command's,
 *    so a renamed host is renamed on the waiting line too.
 *
 * assets/js/terminal.js walks whatever .terminal__line elements it finds and
 * types the .terminal__command inside them, so a line added here is animated
 * without the script knowing anything about it.
 */
function home_terminal_lines(array $lines): string
{
    $out    = '';
    $prompt = HOME_PROMPT_DEFAULT;

    foreach ($lines as $line) {
        $text = (string)($line['text'] ?? '');

        if (($line['kind'] ?? 'output') === 'command') {
            $prompt = trim((string)($line['prompt'] ?? '')) ?: HOME_PROMPT_DEFAULT;
            $out .= '<div class="terminal__line">'
                  . '<span class="terminal__prompt">' . h($prompt) . '</span>'
                  . '<span class="terminal__command">' . h($text) . '</span>'
                  . "</div>\n";
            continue;
        }

        $tone  = (string)($line['tone'] ?? 'plain');
        $class = 'terminal__line terminal__output'
               . ($tone === 'plain' ? '' : ' terminal__output--' . h($tone));

        $out .= '<div class="' . $class . '">' . h($text) . "</div>\n";
    }

    return $out
        . '<div class="terminal__line">'
        . '<span class="terminal__prompt">' . h($prompt) . '</span>'
        . '<span class="terminal__cursor"></span>'
        . "</div>\n";
}

/* ----------------------------------------------------------------- the CTA */

/**
 * The closing heading, which is two lines.
 *
 * Stored as plain text with a newline in it, not as markup with a <br>. The
 * operator gets a two-line textarea and the page gets the line break; nobody
 * has to type a tag, and nothing that arrives from the form is printed as
 * markup. Escaped first, then the newline replaced, because h() leaves
 * newlines alone and so cannot undo the substitution.
 */
function home_cta_title(string $title): string
{
    return str_replace("\n", '<br>', h(trim($title)));
}

/* ------------------------------------------------------------- the artwork */

/**
 * One picture, as the markup the page has always carried.
 *
 * Emitted as a single line with no whitespace between the tags, because
 * <picture> and <img> are inline and a newline between them is a space the
 * browser renders. Same contract as about_picture(): a row with no WebP
 * sibling gets a bare <img> and no <picture> wrapper, and width and height are
 * omitted only when the document does not have them.
 */
function home_picture(array $image, string $class, string $alt): string
{
    $src = trim((string)($image['src'] ?? ''));
    if ($src === '') {
        return '';
    }

    $size = '';
    if (($image['width'] ?? 0) > 0 && ($image['height'] ?? 0) > 0) {
        $size = ' width="' . (int)$image['width'] . '"'
              . ' height="' . (int)$image['height'] . '"';
    }

    $img = '<img class="' . h($class) . '" src="' . h($src) . '"'
         . ' alt="' . h($alt) . '"' . $size
         . ' loading="lazy" decoding="async">';

    $webp = trim((string)($image['webp'] ?? ''));
    if ($webp === '') {
        return $img;
    }

    return '<picture><source srcset="' . h($webp) . '" type="image/webp">'
         . $img . '</picture>';
}

/**
 * A destination card's artwork: one picture, or a light/dark pair.
 *
 * WITH NO DARK IMAGE UPLOADED THIS EMITS EXACTLY ONE PICTURE, with no
 * theme-swap classes and no second element — byte for byte what the page
 * carried before it rendered from a document. That is the case that matters,
 * because it is every card today: the illustrations are black line art on
 * white and the stylesheet deliberately keeps them on a light plate in both
 * colour modes, so a dark half is an option nobody has taken rather than a
 * gap. A page that grew a hidden second <picture> per card to support a
 * feature nobody is using would be paying for it on every visit.
 *
 * Upload a dark half and the pair appears, swapped by CSS rather than by
 * script so the right one is there at first paint, and the dark half carries
 * a modifier that takes it off the light plate.
 *
 * There is deliberately no "dark only" case: a card with a dark image and no
 * light one shows the dark one in both modes, because the alternative is a
 * card with no picture in light mode.
 */
function home_destination_art(array $row): string
{
    $class = 'destination-card__media';
    $alt   = (string)($row['alt'] ?? '');

    $light = is_array($row['image'] ?? null) ? $row['image'] : [];
    $dark  = is_array($row['image_dark'] ?? null) ? $row['image_dark'] : [];

    $hasLight = trim((string)($light['src'] ?? '')) !== '';
    $hasDark  = trim((string)($dark['src'] ?? '')) !== '';

    if (!$hasDark) {
        return home_picture($light, $class, $alt);
    }

    if (!$hasLight) {
        return home_picture($dark, $class . ' ' . $class . '--dark', $alt);
    }

    /* Both halves carry the same alt text, and that is deliberate: exactly one
       is displayed at a time, so a screen reader ignoring CSS would otherwise
       announce the same illustration twice under two different names. */
    return home_picture($light, $class . ' theme-swap--light', $alt)
         . home_picture($dark, $class . ' ' . $class . '--dark theme-swap--dark', $alt);
}

/* ---------------------------------------------------------- structured data */

/**
 * The Service ItemList the page carries in its <head>.
 *
 * Generated rather than literal so the six entries cannot disagree with the
 * six cards a visitor reads. They had: the card said "SOC & CIRT" and the
 * schema said "SOC and CIRT", because the two were maintained by hand in two
 * places. Now a seventh card is a seventh entry by being a seventh card.
 *
 * Hidden cards are absent from both, which is the point of hiding one.
 */
function home_service_schema(array $data, string $origin): array
{
    $items = [];

    foreach (home_shown($data, 'services') as $i => $row) {
        $href = trim((string)($row['href'] ?? ''));

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'item'     => array_filter([
                '@type'       => 'Service',
                'name'        => (string)($row['title'] ?? ''),
                'description' => (string)($row['text'] ?? ''),
                'url'         => $href === '' ? '' : $origin . $href,
                'provider'    => ['@id' => $origin . '/#organization'],
            ], static fn($v): bool => $v !== '' && $v !== []),
        ];
    }

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => (string)($data['services']['schema_name'] ?? ''),
        'description'     => (string)($data['services']['schema_description'] ?? ''),
        'itemListElement' => $items,
    ];
}
