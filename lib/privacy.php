<?php
/**
 * Tech4TIME — privacy policy page data access.
 *
 * Reading the file is lib/store.php; escaping is lib/html.php; the SHAPE of
 * the page is lib/contract.php, which the frontend and the backend hold
 * byte-identical. What is left here is this side's own business with that
 * shape: turning a document into the markup a visitor receives.
 *
 * Nothing here writes. The only thing on this host that writes content at all
 * is api/publish.php, landing a document the backend signed.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":     set on every save
 *     "revision":    monotonic; see contract.php
 *     "status":      shown | hidden -- the whole page; hidden answers 404
 *     "meta":        { title, description, share_title, breadcrumb }
 *     "hero":        { title, subtitle }
 *     "policy":      { label, effective, callout, sections: [ section, ... ] }
 *     "cta":         { status, title, text, items: [ button, ... ] }
 *   }
 *
 * A section is one headed part of the policy, and its id is its ANCHOR:
 *   { id, heading, status, body }   -- body is Markdown source
 *
 * WHAT IS PRINTED BARE
 * Section bodies and callout points go out through md_render()/md_inline(),
 * which escape every text run and emit only a fixed tag vocabulary -- there
 * is no stored markup any more. Everything else goes through h().
 *
 * NO ICONS, AND NO SPRITE
 * The policy body carries no glyph of any kind, so unlike the certifications
 * page there is no second sprite here and unlike the branding page there is no
 * constant to inline. tools/inject_icons.py needs nothing from this file.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';
require_once __DIR__ . '/markdown.php';

const PRIVACY_FILE = __DIR__ . '/../content/privacy.json';

/** The document, with every field the renderer reads guaranteed present. */
function privacy_load(): array
{
    return privacy_normalise(store_read(PRIVACY_FILE) ?? []);
}

/**
 * "2026-08-21" as "Effective 21 August 2026", or '' when it will not read.
 *
 * The field is a date picker, so what arrives is ISO or refusal -- but a
 * document written before the picker, or by hand, can hold anything, and a
 * chip that prints a sentence fragment is worse than no chip. Defensive the
 * same way privacy_effective_date() is.
 */
function privacy_effective_line(string $iso): string
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($iso), $m)
        || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])
    ) {
        return '';
    }
    $stamp = strtotime($m[1] . '-' . $m[2] . '-' . $m[3] . ' UTC');
    if ($stamp === false) {
        return '';
    }
    return 'Effective ' . gmdate('j F Y', $stamp);
}

/**
 * The auto stamp as "Last updated 26 September 2026", or ''.
 *
 * `updated` moves on every save -- typo fixes included -- which is exactly
 * what "last updated" means and exactly what "effective" must never follow.
 * The two chips side by side say what changed and what merely got published.
 */
function privacy_updated_line(string $iso): string
{
    $stamp = strtotime(trim($iso));
    if ($stamp === false) {
        return '';
    }
    return 'Last updated ' . gmdate('j F Y', $stamp);
}

/* --------------------------------------------------------- structured data */

/**
 * The policy itself, as the document it is.
 *
 * NOT A SECOND WebPage NODE, and that is the whole design of this one.
 * schema.org has no type for a privacy policy — WebPage's subtypes are
 * AboutPage, ContactPage, CollectionPage, FAQPage and a handful more, and none
 * of them is this — so emitting a bare second WebPage here would put two nodes
 * on one URL saying the same things, which is the fault that had to be taken
 * off the About page. The page is already described: seo_page_node() gives it a
 * WebPage with its name, its description, its trail and its dateModified.
 *
 * What is NOT described anywhere is the policy, which is a different thing from
 * the page that displays it: a document, published by a named organisation, in
 * force from a date. The date is the reason this exists. "Effective 21 August
 * 2026" is the one fact on the page a machine would want and could not read,
 * and it is nowhere in the graph — dateModified is when the document was last
 * PUBLISHED, which is not when the policy took effect and can differ by months.
 *
 * The date is parsed defensively and dropped when it will not parse. The field
 * is free text an editor types, so it can say anything; a datePublished that is
 * a guess is worse than no datePublished at all.
 */
function privacy_policy_schema(array $data): array
{
    $policy = $data['policy'] ?? [];

    $graph = [
        '@context'   => 'https://schema.org',
        '@type'      => 'CreativeWork',
        '@id'        => seo_url('/pages/privacy-policy/') . '#policy',
        'name'       => (string)($policy['label'] ?? 'Privacy policy'),
        'url'        => seo_url('/pages/privacy-policy/'),
        'about'      => ['@id' => SEO_ORIGIN . '/#organization'],
        'publisher'  => ['@id' => SEO_ORIGIN . '/#organization'],
        'inLanguage' => seo_site()['lang'],
    ];

    $effective = privacy_effective_date((string)($policy['effective'] ?? ''));
    if ($effective !== '') {
        $graph['datePublished'] = $effective;
    }

    return $graph;
}


/**
 * "Effective 21 August 2026" as 2026-08-21, or '' when it will not read.
 *
 * THE DATE IS CUT OUT FIRST, because strtotime() will not read a sentence:
 * measured, "21 August 2026" parses and "Effective 21 August 2026" is false,
 * as is "Last updated: 3 March 2024". Handing it the whole field would have
 * meant this never fired on the wording the policy actually uses, which is a
 * feature that silently does nothing.
 *
 * A DAY IS REQUIRED. "In force since 2026" names a year and no date, and
 * turning that into 2026-01-01 would be inventing the first of January. It
 * returns '' and the graph carries no datePublished, which is the truthful
 * answer.
 *
 * The year is then checked back against the text. That is what stops a string
 * strtotime() only half understood from quietly becoming today's date on every
 * render — a lie that refreshes itself.
 */
function privacy_effective_date(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    /* 2026-08-21, then 21 August 2026, then August 21, 2026. Each needs a day:
       a month and a year alone is not a date. */
    $patterns = [
        '/\b\d{4}-\d{2}-\d{2}\b/',
        '/\b\d{1,2}\s+[A-Za-z]{3,9}\.?\s+\d{4}\b/',
        '/\b[A-Za-z]{3,9}\.?\s+\d{1,2}(?:st|nd|rd|th)?,?\s+\d{4}\b/',
    ];

    $found = '';
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $found = $m[0];
            break;
        }
    }
    if ($found === '') {
        return '';
    }

    $stamp = strtotime($found);
    if ($stamp === false) {
        return '';
    }

    $year = date('Y', $stamp);
    if (!str_contains($text, $year)) {
        return '';
    }

    return date('Y-m-d', $stamp);
}
