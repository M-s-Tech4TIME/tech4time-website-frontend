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
 *     "meta":        { title, description, share_title, breadcrumb }
 *     "hero":        { title, subtitle }
 *     "policy":      { label, effective, callout, sections: [ section, ... ] }
 *     "cta":         { status, title, text, items: [ button, ... ] }
 *   }
 *
 * A section is one headed part of the policy, and its id is its ANCHOR:
 *   { id, heading, status, blocks: [ block, ... ] }
 *
 * A block is one of six shapes, and the KIND decides the markup:
 *   paragraph  note  address     { text }        rich
 *   subheading                   { text }        plain
 *   list                         { rows[] }      rich rows
 *   table                        { caption, columns[2], rows[] }   plain
 *
 * WHY STRUCTURE IS A KIND AND NOT MARKUP
 * rt_sanitise_html() allows nine tags and no heading, no <address> and no
 * <table> among them. A person typing <h3> into a rich field would watch it
 * disappear on save with no way to tell that from a bug. So the renderer owns
 * the structure and the rich field carries only what belongs in a paragraph.
 *
 * WHAT IS PRINTED BARE
 * A paragraph, a note, an address and a list row are rich text and go out
 * unescaped — sanitised on the way in by contract_sanitise(), and again on
 * receipt, because a signature proves where a document came from and not what
 * is inside it. Everything else goes through h().
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

const PRIVACY_FILE = __DIR__ . '/../content/privacy.json';

/** The document, with every field the renderer reads guaranteed present. */
function privacy_load(): array
{
    return privacy_normalise(store_read(PRIVACY_FILE) ?? []);
}

/**
 * One block, as the markup its kind means.
 *
 * FLAT SIBLINGS, DELIBERATELY. Nothing here wraps a section in a container.
 * assets/css/pages/legal.css zeroes the top margin of the first heading with
 * `.legal__body > .legal__heading:first-of-type`, and a wrapper would make
 * that child combinator match nothing — every heading, including the first,
 * would gain a space it should not have. The blocks are emitted at the same
 * depth the hand-written page emitted them.
 *
 * The default arm is unreachable for a normalised document: an unknown kind
 * has already become a paragraph in privacy_block_defaults(), which keeps the
 * words rather than dropping them. It is here because a renderer that trusts
 * its input to be normalised is a renderer that breaks the day it is not.
 */
function privacy_block(array $block): string
{
    $kind = (string)($block['kind'] ?? '');
    $text = (string)($block['text'] ?? '');

    return match ($kind) {
        'paragraph'  => '<p>' . $text . '</p>',
        'note'       => '<p class="legal__notice">' . $text . '</p>',
        'address'    => '<address class="legal__address">' . $text . '</address>',
        'subheading' => '<h3 class="legal__subheading">' . h($text) . '</h3>',
        'list'       => privacy_list($block),
        'table'      => privacy_table($block),
        default      => '',
    };
}

/** A bulleted list. Its rows are rich text; the <li> is ours. */
function privacy_list(array $block): string
{
    $out = '<ul class="legal__list">';

    foreach (privacy_rows_shown($block['rows'] ?? []) as $row) {
        $out .= '<li>' . (string)($row['text'] ?? '') . '</li>';
    }

    return $out . '</ul>';
}

/**
 * A two-column table, and every cell escaped.
 *
 * Plain on purpose. A retention period is a fact, and a link or an emphasis in
 * one changes what the row appears to promise. The <caption> is read out
 * before the table and shown to nobody: without it a screen reader announces
 * "table" and the listener has to infer what it holds from the first cell.
 *
 * The first cell of each row is a <th scope="row">, not a <td>, so a listener
 * moving across a row hears what it is about before hearing the answer.
 */
function privacy_table(array $block): string
{
    $columns = $block['columns'] ?? ['', ''];
    $caption = trim((string)($block['caption'] ?? ''));

    $out = '<div class="legal__table-wrap"><table class="legal__table">';

    if ($caption !== '') {
        $out .= '<caption class="visually-hidden">' . h($caption) . '</caption>';
    }

    $out .= '<thead><tr>';
    foreach ($columns as $column) {
        $out .= '<th scope="col">' . h((string)$column) . '</th>';
    }
    $out .= '</tr></thead><tbody>';

    foreach (privacy_rows_shown($block['rows'] ?? []) as $row) {
        $out .= '<tr><th scope="row">' . h((string)($row['label'] ?? '')) . '</th>'
              . '<td>' . h((string)($row['value'] ?? '')) . '</td></tr>';
    }

    return $out . '</tbody></table></div>';
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
