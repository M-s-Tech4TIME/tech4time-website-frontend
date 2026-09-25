<?php
/**
 * Tech4TIME — what the legal pages share: the tab pills and the page rail.
 *
 * FRONTEND ONLY. Rendered, never stored: the pills and the rail are derived
 * from the documents and the current route on every request, so they cannot
 * go stale. A pasted copy in three page files would drift the day the second
 * document hid itself -- which is why this file exists rather than markup in
 * tools/templates/.
 *
 * Nothing here writes. Like lib/body.php it reads documents off disk and
 * emits markup; words themselves stay in content/*.json.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';

/**
 * Every legal page: route, name, and the document that says if it is shown.
 *
 * Terms and cookies join this list with their phases; the pills, the rail
 * and the sitemap need no other change when they do, because nothing here
 * names a page that has no row.
 */
const LEGAL_PAGES = [
    ['route' => '/pages/privacy-policy/', 'name' => 'Privacy Policy', 'document' => 'privacy'],
];

/** The shown legal pages, in list order. Hidden answers 404 elsewhere too. */
function legal_pages_shown(): array
{
    $out = [];
    foreach (LEGAL_PAGES as $page) {
        try {
            $data = contract_normalise(
                $page['document'], store_read(contract_path($page['document'])) ?? []);
        } catch (Throwable) {
            continue;
        }
        if (($data['status'] ?? 'shown') === 'hidden') {
            continue;
        }
        $out[] = $page;
    }
    return $out;
}

/**
 * The pills switching between the legal pages, or nothing.
 *
 * One pill is not a switch: with a single shown document there is nothing
 * to switch between, so the pills render only at two or more. The current
 * page is a span with aria-current, not a link to itself.
 */
function legal_tabs(string $current): string
{
    $pages = legal_pages_shown();
    if (count($pages) < 2) {
        return '';
    }

    $out = '<nav class="legal__tabs" aria-label="Legal documents"><ul>';
    foreach ($pages as $page) {
        if ($page['route'] === $current) {
            $out .= '<li><span class="legal__tab legal__tab--current" aria-current="page">'
                  . h($page['name']) . '</span></li>';
        } else {
            $out .= '<li><a class="legal__tab" href="' . h($page['route']) . '">'
                  . h($page['name']) . '</a></li>';
        }
    }
    return $out . '</ul></nav>';
}

/**
 * The "On this page" rail: numbered links to the shown sections.
 *
 * Numbers are position, not content: 01, 02, … in render order, zero-padded
 * to two. The anchor is the section's stored id, which never changes even
 * when the heading above it does.
 */
function legal_toc(array $sections): string
{
    $shown = array_values(array_filter(
        is_array($sections) ? $sections : [],
        static fn($s): bool => is_array($s) && ($s['status'] ?? 'shown') !== 'hidden'
            && trim((string)($s['heading'] ?? '')) !== ''
    ));
    if ($shown === []) {
        return '';
    }

    $out = '<nav class="legal__toc" aria-label="On this page">'
         . '<p class="legal__toc-title">On this page</p><ol>';
    foreach ($shown as $i => $section) {
        $out .= '<li><a href="#' . h((string)($section['id'] ?? '')) . '">'
              . '<span class="legal__toc-num">' . sprintf('%02d', $i + 1) . '</span> '
              . h((string)($section['heading'] ?? '')) . '</a></li>';
    }
    return $out . '</ol></nav>';
}
