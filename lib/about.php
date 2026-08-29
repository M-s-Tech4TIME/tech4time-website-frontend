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
 * The logo lockup a story row laid out as 'logo' draws.
 *
 * Two pictures, one for each colour mode, swapped by CSS rather than by script
 * so the right one is there at first paint. These are brand assets that ship
 * with the site, which is why they are named here and not in the document:
 * the layout is a choice an editor makes, the file is not.
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
 * The light/dark wordmark pair, for a story row laid out as 'logo'.
 *
 * Both halves carry the same alt text. That is deliberate and not an
 * oversight: exactly one of them is displayed at a time, so a screen reader
 * that ignores CSS would otherwise announce the logo twice under two
 * different names.
 */
function about_logo_lockup(string $class, string $alt): string
{
    $out = '';
    foreach (ABOUT_LOGO_LOCKUP as $side) {
        $out .= '<picture class="' . h($side['class']) . '">'
              . '<source srcset="' . h($side['stem']) . '.webp" type="image/webp">'
              . '<img class="' . h($class) . '" src="' . h($side['stem']) . '.png"'
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
