<?php
/**
 * Tech4TIME — the milestones page's data access.
 *
 * Reading the file is lib/store.php; escaping and rich-text sanitising is
 * lib/html.php; the SHAPE of the document is lib/contract.php, which the
 * frontend and the backend hold byte-identical. What is left here is this
 * side's own business with that shape — which is the structured data for the
 * page, and the read-through below.
 *
 * TWO PAGES READ THIS ONE DOCUMENT. /pages/milestones/ renders all of it;
 * /pages/company-profile/ renders the most recent MILESTONES_WINDOW years of
 * it and links here for the rest. The window is milestones_recent() in the
 * contract, so both halves of the site agree about what "recent" means.
 *
 * WHAT THE SHAPE IS
 *   {
 *     "updated":    set on every save
 *     "revision":   monotonic; see contract.php
 *     "meta":       the band the SEO screen edits
 *     "hero":       { title, subtitle }
 *     "timeline":   { status, eyebrow, title, lead,
 *                     items[ { id, year, title, text, status } ] }
 *   }
 */

declare(strict_types=1);

require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/company.php';
require_once __DIR__ . '/seo.php';

const MILESTONES_FILE = __DIR__ . '/../content/milestones.json';

/**
 * The milestones as they should be rendered.
 *
 * Never throws. A missing, unreadable or damaged file falls back field by
 * field to what milestones_defaults() ships with.
 *
 * THE READ-THROUGH, AND WHY IT IS NOT A MIGRATION SCRIPT.
 * Until the first save on the new editor the timeline still lives in the
 * company document, where it was written — the heading, the introduction and
 * every entry. Reading through to it rather than moving it by hand means there
 * is no moment, not during a deploy and not between the two halves being
 * released, where the company profile has no timeline on it. The first save on
 * ?s=milestones writes the band here and this stops firing, for good.
 *
 * IT KEYS ON THE REVISION, and the alternatives are both wrong. A missing file
 * is not the test: a fresh host is seeded with content/milestones.json from
 * the defaults, so the file exists on the day the route does. An empty list is
 * not the test either: an operator who deliberately removed every entry would
 * get them all back on the next request, which is the editor refusing to do
 * what it was told. Revision 0 means "nobody has ever saved this", which is
 * exactly the condition — contract_next_revision() mints 1 on the first save
 * and api/publish.php refuses anything below it.
 *
 * THE WHOLE BAND MOVES, not just the rows. The heading, the eyebrow and the
 * introduction are part of the timeline and are edited on the same screen now;
 * carrying the entries and leaving the words behind would have published a
 * page whose introduction had silently reverted to the shipped default.
 * company_milestone_defaults() and milestones_entry_defaults() carry the same
 * five fields, so the rows need no translating on the way across.
 */
function milestones_load(): array
{
    $data = milestones_normalise(store_read(MILESTONES_FILE) ?? []);

    if ((int)$data['revision'] === 0) {
        $company = company_normalise(store_read(COMPANY_FILE) ?? []);
        $band    = $company['milestones'] ?? [];

        $data['timeline'] = [
            'status'  => ($band['status'] ?? 'shown') === 'hidden' ? 'hidden' : 'shown',
            'eyebrow' => (string)($band['eyebrow'] ?? $data['timeline']['eyebrow']),
            'title'   => (string)($band['title'] ?? $data['timeline']['title']),
            'lead'    => (string)($band['lead'] ?? $data['timeline']['lead']),
            'items'   => array_map('milestones_entry_defaults',
                                   $band['items'] ?? []),
        ];

        $data = milestones_identify($data);
    }

    return $data;
}

/* --------------------------------------------------------- structured data */

/**
 * The timeline as an ItemList of Events.
 *
 * Takes the ROWS and not the document, because both pages draw it and they
 * draw different numbers of them: the company profile's graph must describe
 * the five years it shows, not the twenty it does not. A graph that lists what
 * the markup does not is a page saying two things about itself.
 *
 * Only rows a visitor can see, for the same reason — company_page_schema()
 * made that call first and this is the same one.
 */
function milestones_event_list(array $rows, string $name): array
{
    $events = [];
    foreach ($rows as $row) {
        $year  = trim((string)$row['year']);
        $event = [
            '@type' => 'Event',
            'name'  => (string)$row['title'],
            'description' => rt_plain((string)$row['text']),
        ];
        if (preg_match('/^\d{4}$/', $year)) {
            $event['startDate'] = $year;
        }
        $events[] = $event;
    }

    if (!$events) {
        return [];
    }

    return [
        '@type' => 'ItemList',
        'name'  => $name,
        'itemListElement' => array_map(
            static fn(int $i, array $e): array => [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'item'     => $e,
            ],
            array_keys($events),
            $events
        ),
    ];
}

/** The CollectionPage graph for /pages/milestones/. */
function milestones_page_schema(array $data): array
{
    $graph = [
        '@context' => 'https://schema.org',
        '@type'    => 'CollectionPage',
        'url'      => seo_url('/pages/milestones/'),
        'name'     => (string)$data['hero']['title'],
        'description' => rt_plain((string)$data['meta']['description']),
        'about'    => [
            '@type' => 'Organization',
            'name'  => seo_site()['name'],
            'url'   => seo_url('/'),
            'foundingDate' => seo_identity()['founded'],
        ],
    ];

    /* Only when the band is actually drawn. milestones_shown() filters ROWS,
       not the band, so a hidden timeline still has rows to hand — and a graph
       listing entries the markup does not carry is a page saying two different
       things about itself. Hidden is hidden from a crawler too. */
    $list = milestones_band_shown($data, 'timeline')
        ? milestones_event_list(milestones_shown($data, 'timeline'),
                                (string)$data['timeline']['title'])
        : [];
    if ($list) {
        $graph['mainEntity'] = $list;
    }

    return $graph;
}
