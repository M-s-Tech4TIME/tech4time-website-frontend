<?php
/**
 * Tech4TIME — contact page data access.
 *
 * Reading and writing the file is lib/store.php; escaping and rich-text
 * sanitising is lib/html.php; the SHAPE of the contact page is
 * lib/contract.php, which the frontend and the backend hold byte-identical.
 * What is left here is this side's own business with that shape.
 *
 * On THIS side that is: the ContactPage structured data, the flag <picture>,
 * and how a reach row turns into a link. Validation, the flag picker and
 * saving are the backend's — nothing here writes the contact page. The only
 * thing on this host that writes content at all is api/publish.php, landing a
 * document the backend signed.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":        set on every save
 *     "revision":       monotonic; see contract.php
 *     "footer_synced":  fingerprint of the details as last written into the
 *                       site-wide footer — see contact_fingerprint()
 *     "meta":    { title, description, share_title }
 *     "hero":    { title, subtitle }
 *     "form":    { title, lead, subject_hint, note, service_types[] }
 *     "reach":   { status, title,
 *                  items[ { icon, label, type, values[], text, status } ] }
 *     "offices": { status, eyebrow, title, lead,
 *                  items[ { name, flag, image{}, address, phones[], hours,
 *                  languages[], status, schema{} } ] }
 *
 *   A band's status and a row's are separate switches and both are honoured:
 *   contact_shown_reach() and contact_shown_offices() answer for both, so the
 *   structured data cannot advertise a band the page does not draw.
 *
 *   An office has a flag TWICE: 'flag' is a slug naming a file that ships with
 *   the public site, and 'image' is an uploaded picture. The upload wins when
 *   it is set; the slug is what the three original offices still use.
 *   }
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';

const CONTACT_FILE = __DIR__ . '/../content/contact.json';
const CONTACT_FLAG_DIR = __DIR__ . '/../assets/images/flags';

/* Raster formats a flag may be supplied in. A matching .webp beside it is used
   automatically when it exists; there is no build step on the host, so one
   dropped into the folder by hand has to work on its own. */
const CONTACT_FLAG_FORMATS = ['jpg', 'jpeg', 'png'];

/* ------------------------------------------------------------------- read */

/**
 * Load the file, or the shipped defaults if it is missing or unreadable.
 *
 * Never throws. A contact page showing the addresses it was deployed with is
 * wrong only if they have since changed; a contact page showing a PHP error
 * gives a visitor no way to reach anyone at all.
 */
function contact_load(): array
{
    return contact_normalise(store_read(CONTACT_FILE) ?? []);
}

/* -------------------------------------------------------------- rendering */

/**
 * The href for one of a row's values, or null when it is not a link.
 *
 * tel: wants the number without the spaces a human reads it by, and dialling
 * is the whole point of the link — so the separators come out here while the
 * value stays written the way it should be shown.
 */
function contact_reach_href(array $item, string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    switch ($item['type'] ?? 'text') {
        case 'email':
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $value : null;
        case 'phone':
            return 'tel:' . contact_tel($value);
        case 'url':
            return rt_safe_href($value);
        default:
            return null;
    }
}

/**
 * What one of a row's values reads as.
 *
 * The row's own link text stands in for the value, but only when the row has
 * a single value: three numbers all reading "Tech4TIME" would be three links
 * nobody can tell apart.
 */
function contact_reach_text(array $item, string $value): string
{
    $text = trim((string)($item['text'] ?? ''));
    return ($text !== '' && count($item['values'] ?? []) === 1) ? $text : trim($value);
}

/**
 * The <picture> for one office's flag, or '' when it has none.
 *
 * width and height come from the file itself rather than from the data,
 * because they are the file's business and because a flag added by hand has
 * nobody to type them in. They are not decoration: without them the office
 * cards jump as each image arrives.
 *
 * The .webp source is emitted only when the file is actually there. There is
 * no build step on the host to make one.
 */
function contact_flag_picture(array $office): string
{
    /* AN UPLOADED FLAG WINS. It is the only kind an editor can add: the slug
       below names a file that ships with this repository, so a fourth country
       needed a developer and a deploy until this existed. The slug still
       renders for the three offices that have one, which is why both are here
       rather than one replacing the other. */
    $image = is_array($office['image'] ?? null) ? $office['image'] : [];
    $src = trim((string)($image['src'] ?? ''));

    if ($src !== '') {
        $alt = 'Flag of ' . trim((string)($office['name'] ?? ''));
        $webp = trim((string)($image['webp'] ?? ''));
        $size = ((int)($image['width'] ?? 0) > 0 && (int)($image['height'] ?? 0) > 0)
            ? ' width="' . (int)$image['width'] . '" height="' . (int)$image['height'] . '"'
            : '';

        return '<picture class="office__flag-wrap">'
             . ($webp !== '' ? '<source srcset="' . h($webp) . '" type="image/webp">' : '')
             . '<img class="office__flag" src="' . h($src) . '"'
             . ' alt="' . h($alt) . '"' . $size
             . ' loading="lazy" decoding="async"></picture>';
    }

    $flag = trim((string)($office['flag'] ?? ''));
    if ($flag === '' || !preg_match('/^[a-z0-9-]+$/', $flag)) {
        return '';
    }

    $raster = '';
    foreach (CONTACT_FLAG_FORMATS as $ext) {
        if (is_file(CONTACT_FLAG_DIR . '/' . $flag . '.' . $ext)) {
            $raster = $flag . '.' . $ext;
            break;
        }
    }
    if ($raster === '') {
        return '';
    }

    $size = @getimagesize(CONTACT_FLAG_DIR . '/' . $raster);
    $dimensions = $size
        ? ' width="' . (int)$size[0] . '" height="' . (int)$size[1] . '"'
        : '';

    $alt = 'Flag of ' . trim((string)($office['name'] ?? ''));

    $webp = is_file(CONTACT_FLAG_DIR . '/' . $flag . '.webp')
        ? '<source srcset="/assets/images/flags/' . h($flag) . '.webp" type="image/webp">'
        : '';

    return '<picture class="office__flag-wrap">' . $webp
         . '<img class="office__flag" src="/assets/images/flags/' . h($raster) . '"'
         . ' alt="' . h($alt) . '"' . $dimensions
         . ' loading="lazy" decoding="async"></picture>';
}

/* -------------------------------------------------------- structured data */

/**
 * A JSON value, encoded to sit inside hand-written JSON at a given depth.
 *
 * The Organization graph on the contact page is a literal object with two
 * arrays spliced into it. json_encode() indents from column zero, so without
 * this the generated arrays start flush left inside an object indented six
 * spaces — valid JSON, and unreadable next to the lines around it, which is
 * how a hand-edited block acquires its first mistake.
 *
 * An empty array encodes as [] and that is the right answer: a band switched
 * off has no addresses, and "address": [] says so without inventing one.
 */
function contact_ld_indent(array $value, int $spaces): string
{
    $json = json_encode(
        $value,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        return '[]';
    }

    $pad = str_repeat(' ', $spaces);

    /* Every line but the first, which is already sitting after the key. */
    return str_replace("\n", "\n" . $pad, $json);
}

/** One schema.org PostalAddress per shown office that has enough to make one. */
function contact_addresses(array $data): array
{
    $out = [];
    foreach (contact_shown_offices($data) as $office) {
        $s = $office['schema'];
        $address = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => trim((string)$s['street']),
            'addressLocality' => trim((string)$s['locality']),
            'addressRegion'   => trim((string)$s['region']),
            'postalCode'      => trim((string)$s['postal_code']),
            'addressCountry'  => strtoupper(trim((string)$s['country'])),
        ], static fn(string $v): bool => $v !== '');

        /* A country on its own is not an address anyone can post to. */
        if (count($address) > 2) {
            $out[] = $address;
        }
    }
    return $out;
}

/**
 * One schema.org ContactPoint per shown office that has a phone.
 *
 * Per office rather than one for the company, because areaServed is the field
 * that makes a number useful to a search engine, and it is only true of the
 * office the number rings.
 */
function contact_points(array $data): array
{
    $email = contact_email($data);
    $out = [];

    /* ONE PER NUMBER, NOT ONE PER OFFICE. It was the office's first phone and
       the rest were not advertised at all — an office listing three numbers
       had two of them reachable on the page and invisible to a search engine.
       areaServed and the languages come from the office either way, so a
       second number from the same office is the same point with a different
       line on it. */
    foreach (contact_shown_offices($data) as $office) {
        $country = strtoupper(trim((string)$office['schema']['country']));

        foreach ($office['phones'] as $phone) {
            $point = [
                '@type'       => 'ContactPoint',
                'telephone'   => contact_tel((string)$phone),
                'contactType' => 'customer service',
            ];
            if ($email !== '') {
                $point['email'] = $email;
            }
            if ($country !== '') {
                $point['areaServed'] = $country;
            }
            $point['availableLanguage'] = $office['languages'] ?: ['English'];
            $out[] = $point;
        }
    }

    return $out;
}

/** The ContactPage graph for this page. */
function contact_page_schema(array $data): array
{
    $entity = array_filter([
        '@type' => 'Organization',
        'name'  => 'Tech4TIME',
        'url'   => 'https://tech4time.bd/',
        'email' => contact_email($data),
    ], static fn($v): bool => $v !== '');

    $points = contact_points($data);
    if ($points) {
        $entity['contactPoint'] = $points;
    }
    $addresses = contact_addresses($data);
    if ($addresses) {
        $entity['address'] = $addresses;
    }

    return [
        '@context'   => 'https://schema.org',
        '@type'      => 'ContactPage',
        'url'        => 'https://tech4time.bd/pages/contact/',
        'name'       => 'Contact Tech4TIME',
        'mainEntity' => $entity,
    ];
}
