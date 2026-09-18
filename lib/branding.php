<?php
/**
 * Tech4TIME — branding & advertisement page data access.
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
 *     "meta":        { title, description, share_title }
 *     "hero":        { title, subtitle }
 *     "assets":      { status, eyebrow, title, lead, items: [ asset, ... ] }
 *     "legal":       { status, title, items: [ { id, text, status } ] }
 *     "cta":         { status, title, text, items: [ button, ... ] }
 *   }
 *
 * An asset is one logo variant — a card with a preview and the files to take:
 *   { id, title, text, alt, plate, status,
 *     image: { src, webp, width, height },
 *     files: [ { id, label, filename, status,
 *                file: { src, webp, width, height } } ] }
 *
 * THREE THINGS ARE DRAWN, NOT STORED
 * The dimensions in a file's meta line come off that file's own record, so
 * they cannot claim 1600 x 570 about something that is no longer that size.
 * "Download PNG" states the file's own format. The glyph on every button is
 * one constant. See branding_meta_line() and BRANDING_DOWNLOAD_GLYPH.
 *
 * THE PREVIEW AND THE DOWNLOAD ARE NOT THE SAME PICTURE
 * 'image' is the small thing drawn on the card; 'files' is what a visitor
 * came for, and on the page as it ships those are 800px and 1600px versions
 * of the same mark. See branding_asset_defaults() in lib/contract.php.
 *
 * NOTHING HERE RENDERS AN SVG
 * A vector file may be offered for download — it is linked, never drawn. The
 * preview stays raster, so no SVG is ever parsed by a visitor's browser as a
 * consequence of loading this page. See ADR 0019.
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/html.php';

const BRANDING_FILE = __DIR__ . '/../content/branding.json';

/** The document, with every field the renderer reads guaranteed present. */
function branding_load(): array
{
    return branding_normalise(store_read(BRANDING_FILE) ?? []);
}

/**
 * A preview picture, or '' when the asset has none.
 *
 * The same function as about_picture() and company_picture(), for the same
 * reasons: width and height come from the document and are OMITTED rather
 * than guessed when it does not carry them, which is why this site's
 * Cumulative Layout Shift is zero rather than nearly zero; and an empty
 * 'webp' means "no WebP sibling, emit a bare <img>" rather than a <picture>
 * wrapping a source that points at nothing.
 *
 * One line, with no whitespace between the tags, because <picture> and <img>
 * are inline and a newline between them is a space on the page.
 */
function branding_picture(array $image, string $class, string $alt): string
{
    $src = trim((string)($image['src'] ?? ''));
    if ($src === '') {
        return '';
    }

    $size = '';
    if (($image['width'] ?? 0) > 0 && ($image['height'] ?? 0) > 0) {
        $size = ' width="' . (int)$image['width'] . '" height="' . (int)$image['height'] . '"';
    }

    /* One picture at several widths, when this one was stored that way and
       the slot says how wide it is drawn. Both halves or neither: see
       contract_picture_ladder(). The DOWNLOADS on this page are not drawn and
       do not ladder -- there the file is the deliverable. */
    $ladder = contract_picture_ladder($image, 'branding.asset');
    $rungs  = $ladder['srcset'] === '' ? ''
            : ' srcset="' . h($ladder['srcset']) . '"'
            . ' sizes="' . h($ladder['sizes']) . '"';

    $img = '<img class="' . h($class) . '" src="' . h($src) . '"' . $rungs
         . ' alt="' . h($alt) . '"' . $size . ' loading="lazy" decoding="async">';

    $webp = trim((string)($image['webp'] ?? ''));
    if ($webp === '') {
        return $img;
    }

    $source = $ladder['webp_srcset'] === ''
        ? '<source srcset="' . h($webp) . '" type="image/webp">'
        : '<source srcset="' . h($ladder['webp_srcset']) . '"'
        . ' sizes="' . h($ladder['sizes']) . '" type="image/webp">';

    return '<picture>' . $source . $img . '</picture>';
}

/**
 * The glyph on a download button.
 *
 * A constant rather than a field — see BRANDING_DOWNLOAD_GLYPH — and written
 * out here as a literal href="#arrow-down" only in the page itself, where
 * tools/inject_icons.py can see it. This function is not where the scanner
 * looks, so it takes the name rather than spelling it.
 */
function branding_glyph(string $name): string
{
    return '<svg class="icon icon--sm" aria-hidden="true" focusable="false">'
         . '<use href="#' . h($name) . '"></use></svg>';
}

/* --------------------------------------------------------- structured data */

/**
 * The CollectionPage graph for /pages/branding-and-advertisement/.
 *
 * This page is a press kit: named marks, each with files somebody may download
 * and terms on what they may do with them. That is an ordinary, well-mapped
 * thing -- a collection of MediaObjects with contentUrl, encodingFormat and
 * dimensions -- and the page had no structured data at all, so a crawler had
 * nothing to say about it beyond the WebPage node every page carries.
 *
 * The DOWNLOAD is the entity, not the preview. Each asset's image is what the
 * page draws; each file under it is what a person actually takes away, and they
 * are different bytes at different sizes. So an asset becomes an ImageObject
 * with one encoding per shown file, which is what encodingFormat and contentUrl
 * are for.
 *
 * SHOWN BANDS, SHOWN ASSETS, SHOWN FILES -- three levels, because the editor
 * offers a switch at all three and the markup honours every one of them.
 */
function branding_page_schema(array $data): array
{
    $graph = [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        'url'         => seo_url('/pages/branding-and-advertisement/'),
        'name'        => (string)($data['hero']['title'] ?? ''),
        'description' => rt_plain((string)($data['meta']['description'] ?? '')),
        'about'       => ['@id' => SEO_ORIGIN . '/#organization'],
    ];

    if (!branding_band_shown($data, 'assets')) {
        return $graph;
    }

    $marks = [];
    foreach (branding_rows_shown($data['assets']['items'] ?? []) as $asset) {
        $title = trim((string)($asset['title'] ?? ''));
        if ($title === '') {
            continue;
        }

        $mark = [
            '@type'   => 'ImageObject',
            'name'    => $title,
            'caption' => rt_plain((string)($asset['text'] ?? '')),
        ];

        $alt = trim((string)($asset['alt'] ?? ''));
        if ($alt !== '') {
            $mark['description'] = $alt;
        }

        $encodings = [];
        foreach (branding_rows_shown($asset['files'] ?? []) as $file) {
            $src = trim((string)($file['file']['src'] ?? ''));
            if ($src === '') {
                continue;
            }

            $encoding = [
                '@type'      => 'MediaObject',
                'name'       => trim((string)($file['label'] ?? '')),
                'contentUrl' => seo_url($src),
            ];

            $type = branding_media_type($src);
            if ($type !== '') {
                $encoding['encodingFormat'] = $type;
            }
            if ((int)($file['file']['width'] ?? 0) > 0) {
                $encoding['width']  = (int)$file['file']['width'];
                $encoding['height'] = (int)$file['file']['height'];
            }

            $encodings[] = $encoding;
        }

        if ($encodings) {
            $mark['encoding'] = $encodings;
            /* The largest rendition doubles as the mark's own contentUrl, so a
               consumer that reads no further than ImageObject still gets a
               usable file rather than nothing. */
            $mark['contentUrl'] = $encodings[0]['contentUrl'];
        }

        $marks[] = $mark;
    }

    if ($marks) {
        $graph['mainEntity'] = [
            '@type'           => 'ItemList',
            'name'            => (string)($data['assets']['title'] ?? ''),
            'numberOfItems'   => count($marks),
            'itemListElement' => array_map(
                static fn(int $i, array $m): array => [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'item'     => $m,
                ],
                array_keys($marks),
                $marks
            ),
        ];
    }

    return $graph;
}


/**
 * The media type of a downloadable file, from its extension.
 *
 * A short map rather than a guess: these are the formats this page offers, and
 * an encodingFormat naming a type the file is not is worse than none. Anything
 * else returns '' and the encoding simply carries no type.
 */
function branding_media_type(string $src): string
{
    return match (strtolower(pathinfo($src, PATHINFO_EXTENSION))) {
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
        default => '',
    };
}
