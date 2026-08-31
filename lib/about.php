<?php
/**
 * Tech4TIME — about page data access.
 *
 * Reading the file is lib/store.php; escaping and rich-text sanitising is
 * lib/html.php; the SHAPE of the page is lib/contract.php, which the frontend
 * and the backend hold byte-identical. What is left here is this side's own
 * business with that shape.
 *
 * On THIS side that is: the AboutPage structured data, the <picture> a story
 * row turns into, and the scroll-reveal markers its prose carries. Validation,
 * the uploads and saving are the backend's — nothing here writes the about
 * page. The only thing on this host that writes content at all is
 * api/publish.php, landing a document the backend signed.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":     set on every save
 *     "revision":    monotonic; see contract.php
 *     "meta":        { title, description, share_title }
 *     "hero":        { title, subtitle }
 *     "story":       { status,
 *                      items[ { id, heading, body, layout, side, alt,
 *                               image{}, status } ] }
 *     "specialties": { status, title, interval,
 *                      items[ { id, icon, title, text, status } ] }
 *     "whyus":       { status, title,
 *                      items[ { id, icon, title, text, status } ] }
 *     "cta":         { status, title, label, href, icon }
 *   }
 *
 * and an image{} is { src, webp, width, height } — see contract_image_defaults().
 *
 * "body" is sanitised HTML, one or two <p> elements. Everything else is plain
 * text and goes through h().
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';

const ABOUT_FILE = __DIR__ . '/../content/about.json';

/**
 * The lockup that ships with the site, used when a row has uploaded none.
 *
 * Two pictures, one for each colour mode, swapped by CSS rather than by script
 * so the right one is there at first paint.
 */
const ABOUT_LOGO_LOCKUP = [
    ['class' => 'theme-swap--light', 'stem' => '/assets/images/logo/logo-light-540'],
    ['class' => 'theme-swap--dark',  'stem' => '/assets/images/logo/logo-dark-540'],
];

const ABOUT_LOGO_WIDTH  = 540;
const ABOUT_LOGO_HEIGHT = 192;

/**
 * The about page as it should be rendered.
 *
 * Never throws. A missing, unreadable or damaged file falls back field by
 * field to what about_defaults() ships with, so the page is stale at worst
 * and never blank.
 */
function about_load(): array
{
    return about_normalise(store_read(ABOUT_FILE) ?? []);
}

/* ------------------------------------------------------------- the artwork */

/**
 * One picture, as the markup the page has always carried.
 *
 * Emitted as a single line with no whitespace between the tags, because
 * <picture> and <img> are inline and a newline between them is a space the
 * browser renders. Same contract as company_picture(): a row with no WebP
 * sibling gets a bare <img> and no <picture> wrapper, and width and height are
 * omitted only when the document does not have them.
 */
function about_picture(array $image, string $class, string $alt): string
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
 * A photograph row's artwork: one picture, or a light/dark pair.
 *
 * WITH NO DARK HALF UPLOADED THIS EMITS EXACTLY ONE PICTURE, with no
 * theme-swap classes and no second element — what the page carried before the
 * dark slot existed. That is the case for every row today: the illustrations
 * are black line art and .about-split__image keeps them on a white plate in
 * both colour modes, so a dark half is an option nobody has taken rather than
 * a gap. A page that grew a hidden second <picture> per row to support a
 * feature nobody is using would be paying for it on every visit.
 *
 * Upload a dark half and the pair appears, swapped by CSS rather than by
 * script so the right one is there at first paint, and the dark half carries
 * a modifier that takes it off the white plate.
 *
 * This is home_destination_art() in the frontend's lib/home.php, for the row
 * shape this page uses. The two are deliberately separate rather than shared:
 * they take different classes, and a helper parameterised by class name is a
 * helper that reads as if the two pages must always agree.
 *
 * There is deliberately no "dark only" case: a row with a dark image and no
 * light one shows the dark one in both modes, because the alternative is a row
 * with no picture in light mode.
 */
function about_photograph(array $row): string
{
    $class = 'about-split__image';
    $alt   = (string)($row['alt'] ?? '');

    $light = is_array($row['image'] ?? null) ? $row['image'] : [];
    $dark  = is_array($row['image_dark'] ?? null) ? $row['image_dark'] : [];

    $hasLight = trim((string)($light['src'] ?? '')) !== '';
    $hasDark  = trim((string)($dark['src'] ?? '')) !== '';

    if (!$hasDark) {
        return about_picture($light, $class, $alt);
    }

    if (!$hasLight) {
        return about_picture($dark, $class . ' ' . $class . '--dark', $alt);
    }

    /* Both halves carry the same alt text, and that is deliberate: exactly one
       is displayed at a time, so a screen reader ignoring CSS would otherwise
       announce the same illustration twice under two different names. */
    return about_picture($light, $class . ' theme-swap--light', $alt)
         . about_picture($dark, $class . ' ' . $class . '--dark theme-swap--dark', $alt);
}

/**
 * The light/dark wordmark pair, for a story row laid out as 'logo'.
 *
 * Each half is the row's own upload if it has one, and the lockup that ships
 * with the site otherwise. The fallbacks are per-half and asymmetric on
 * purpose:
 *
 *   nothing uploaded   both halves are the shipped lockup
 *   light only         the uploaded one is used in BOTH modes
 *   both               each mode gets its own
 *
 * The middle case is the one worth explaining. Falling back to the shipped
 * DARK logo there would put the old mark beside the new one, which is the one
 * outcome nobody wants from "we changed our logo". Using the new light logo on
 * a dark background may read poorly; showing the previous brand does not read
 * poorly, it is wrong. The editor says so and offers the second slot.
 *
 * Both halves carry the same alt text. That is deliberate: exactly one is
 * displayed at a time, so a screen reader ignoring CSS would otherwise
 * announce the logo twice under two different names.
 */
function about_logo_lockup(array $row, string $class): string
{
    $alt   = (string)($row['alt'] ?? '');
    $light = is_array($row['image'] ?? null) ? $row['image'] : [];
    $dark  = is_array($row['image_dark'] ?? null) ? $row['image_dark'] : [];

    if (trim((string)($light['src'] ?? '')) === '') {
        $light = [];
    }
    if (trim((string)($dark['src'] ?? '')) === '') {
        $dark = $light;
    }

    $halves = [
        ['class' => 'theme-swap--light', 'image' => $light, 'stem' => ABOUT_LOGO_LOCKUP[0]['stem']],
        ['class' => 'theme-swap--dark',  'image' => $dark,  'stem' => ABOUT_LOGO_LOCKUP[1]['stem']],
    ];

    $out = '';
    foreach ($halves as $half) {
        if ($half['image'] !== []) {
            /* An uploaded half is an ordinary picture, so it is emitted by the
               ordinary picture function -- including the bare <img> when there
               is no WebP sibling. The theme class has to go on the wrapper,
               and about_picture() does not always emit one, so a row with no
               WebP is wrapped here instead. */
            $picture = about_picture($half['image'], $class, $alt);
            $out .= str_starts_with($picture, '<picture>')
                ? '<picture class="' . h($half['class']) . '">' . substr($picture, 9)
                : '<picture class="' . h($half['class']) . '">' . $picture . '</picture>';
            continue;
        }

        $out .= '<picture class="' . h($half['class']) . '">'
              . '<source srcset="' . h($half['stem']) . '.webp" type="image/webp">'
              . '<img class="' . h($class) . '" src="' . h($half['stem']) . '.png"'
              . ' alt="' . h($alt) . '"'
              . ' width="' . ABOUT_LOGO_WIDTH . '" height="' . ABOUT_LOGO_HEIGHT . '"'
              . ' loading="lazy" decoding="async">'
              . '</picture>';
    }

    return $out;
}

/* ---------------------------------------------------------------- the prose */

/**
 * Put the scroll-reveal markers back on each paragraph of a story body.
 *
 * The page has always revealed a story section one paragraph at a time, and
 * the body is now a single rich-text field — so the markers cannot be typed
 * into it and cannot be written into the template either, because how many
 * paragraphs there are is a property of the content.
 *
 * WHY THIS IS SAFE. It runs on the output of rt_sanitise_html(), which has
 * already reduced the markup to a known set of tags with a known set of
 * attributes; it never runs on anything a browser sent. And it only ever adds
 * two valueless attributes to an opening <p>. If it matched nothing at all,
 * the paragraphs would simply arrive without a reveal — visible, in order, and
 * not animated. It cannot fail into invisible content.
 *
 * tools/apply_reveals.py used to own these markers and no longer does: it
 * reports and skips any page that builds part of itself with a PHP loop, which
 * this page now is. See docs/10-development/frontend/motion.md.
 */
function about_reveal_paragraphs(string $html): string
{
    return preg_replace(
        '/<p(?=[\s>])(?![^>]*\bdata-reveal\b)/i',
        '<p data-reveal data-reveal-delay',
        $html
    ) ?? $html;
}

/* --------------------------------------------------------- structured data */

/**
 * The AboutPage graph for this page.
 *
 * Kept here rather than in the contract because the backend does not render
 * the page and has no use for it — the same line careers_job_posting(),
 * contact_page_schema() and company_page_schema() sit on.
 *
 * The company profile emits an AboutPage too. Two are fine: they carry
 * different @id values and describe different pages about the same
 * organisation, which is what @id is for.
 */
function about_page_schema(array $data): array
{
    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'AboutPage',
        '@id'         => 'https://tech4time.bd/pages/about/#webpage',
        'url'         => 'https://tech4time.bd/pages/about/',
        'name'        => (string)$data['meta']['share_title'],
        'description' => rt_plain((string)$data['meta']['description']),
        'isPartOf'    => ['@id' => 'https://tech4time.bd/#website'],
        'about'       => ['@id' => 'https://tech4time.bd/#organization'],
        'inLanguage'  => 'en',
    ];
}
