<?php
/**
 * Tech4TIME — the sitemap.
 *
 * Served at /sitemap.xml, which is the address robots.txt names and the one
 * submitted to Search Console; .htaccess section 3 rewrites that URL here. The
 * address did not change when this stopped being a static file, and it must
 * not: a sitemap is a URL a crawler remembers.
 *
 * WHY IT IS NO LONGER A STATIC FILE
 * A service added in the editor at admin.tech4time.bd has no file in this
 * repository — it is a row in content/services.json — so a hand-maintained
 * list could never contain it. It would be published, be reachable, be linked
 * from the services index, and be absent from the one file that tells a
 * crawler it exists. Every page that renders from a document is listed from
 * that document here instead.
 *
 * ADDING A PAGE STILL MEANS ADDING A LINE. The pages that are files in this
 * repository are listed in STATIC below, exactly as they were in sitemap.xml,
 * and docs/10-development/frontend/adding-a-page.md still says to add one.
 * What is generated is only what a developer cannot know: the services.
 *
 * LASTMOD IS READ FROM THE DOCUMENT, NOT WRITTEN BY HAND
 * Five pages besides the services render from content/ and change without a
 * deploy, so a date typed into a file was wrong the moment the editor was next
 * used. Each of those takes its lastmod from its document's own `updated`
 * stamp, which api/publish.php sets when the content arrives. A page that is
 * genuinely a static file keeps a hand-set date, because nothing else knows.
 *
 * IT MUST NOT BE ABLE TO FAIL
 * A crawler asking for the sitemap gets a sitemap. A document that is missing
 * or unreadable falls back to the date beside it rather than throwing, so the
 * worst case is a stale date on one line, not a 500 on the file that tells
 * search engines the site exists.
 */

declare(strict_types=1);

require __DIR__ . '/lib/services.php';

const SITEMAP_ORIGIN = 'https://tech4time.bd';

/**
 * The day a document was last published, as YYYY-MM-DD.
 *
 * Falls back to $fallback for anything it cannot read or does not understand,
 * so a sitemap is still served when a document is missing.
 */
function sitemap_updated(string $document, string $fallback): string
{
    $path = contract_path($document);
    if (!is_file($path)) {
        return $fallback;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return $fallback;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !is_string($data['updated'] ?? null)) {
        return $fallback;
    }

    $day = substr($data['updated'], 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : $fallback;
}

/*
 * The pages that are files in this repository.
 *
 *   path, document (null when the page is static), fallback date,
 *   changefreq, priority
 *
 * The dates are the ones sitemap.xml carried, kept as the fallback so a page
 * whose document cannot be read still reports something true of the last
 * deploy. The services index is here because the page is a file; the six or
 * more service pages below it are not, because they are rows.
 */
const SITEMAP_STATIC = [
    ['/',                                  'home',     '2026-08-20', 'weekly',  '1.0'],
    ['/pages/services/',                   'services', '2026-08-20', 'weekly',  '0.9'],
    ['__services__',                       null,       '',           '',        ''],
    ['/pages/about/',                      'about',    '2026-08-20', 'monthly', '0.8'],
    ['/pages/company-profile/',            'company',  '2026-08-20', 'monthly', '0.7'],
    ['/pages/careers/',                    'careers',  '2026-08-21', 'weekly',  '0.7'],
    ['/pages/contact/',                    'contact',  '2026-08-20', 'yearly',  '0.7'],
    ['/pages/resource-certifications/',    null,       '2026-08-21', 'monthly', '0.6'],
    ['/pages/branding-and-advertisement/', null,       '2026-08-21', 'yearly',  '0.4'],
    ['/pages/privacy-policy/',             null,       '2026-08-21', 'yearly',  '0.3'],
];

/* Every service page is the same kind of page, so all of them are described
   the same way. priority is advisory and Google ignores it outright; making it
   editable per page is plans/seo-management.md's job, not a table kept here
   that a seventh service could not be added to. */
const SITEMAP_SERVICE_CHANGEFREQ = 'monthly';
const SITEMAP_SERVICE_PRIORITY   = '0.9';

$data     = services_load();
$services = sitemap_updated('services', '2026-08-20');

$entries = [];
foreach (SITEMAP_STATIC as [$path, $document, $fallback, $changefreq, $priority]) {
    if ($path === '__services__') {
        foreach (services_rows_shown(services_all($data)) as $service) {
            /* The same shape pages/services/detail.php will accept. A service
               row exists from the moment it is added in the editor, and a slug
               it has not been given yet would otherwise be listed here as
               /pages/services// — an address that answers 404. A sitemap
               naming a URL that does not resolve is a crawl error reported
               against the whole site, so it is left out until it is real. */
            $slug = is_string($service['slug'] ?? null) ? $service['slug'] : '';
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                continue;
            }

            $entries[] = [
                '/pages/services/' . $slug . '/',
                $services,
                SITEMAP_SERVICE_CHANGEFREQ,
                SITEMAP_SERVICE_PRIORITY,
            ];
        }
        continue;
    }

    $entries[] = [
        $path,
        $document === null ? $fallback : sitemap_updated($document, $fallback),
        $changefreq,
        $priority,
    ];
}

/* Not text/html: a crawler is entitled to refuse a sitemap that arrives as one,
   and the site sends X-Content-Type-Options: nosniff, so nothing will guess. */
header('Content-Type: application/xml; charset=UTF-8');

/**
 * XML, not HTML. Escapes the five characters that matter in an XML text node.
 */
function x(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<?xml-stylesheet type="text/xsl" href="/assets/xsl/sitemap.xsl"?>
<!--
  Tech4TIME sitemap.

  GENERATED ON REQUEST by sitemap.php, which is the file to change. Editing
  what you are reading changes nothing: it is written out afresh every time
  this URL is asked for.

  The pages that are files in this repository are listed there by hand, and
  adding a page still means adding a line —
  docs/10-development/frontend/adding-a-page.md says so. The service pages are
  read from content/services.json, because a service added in the editor has
  no file here to notice.

  The xml-stylesheet line above is what a BROWSER uses to render this as a
  readable table — assets/xsl/sitemap.xsl. Crawlers ignore it and read the
  <urlset> below, so it changes nothing about how the site is indexed.
-->
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($entries as [$path, $lastmod, $changefreq, $priority]): ?>

  <url>
    <loc><?= x(SITEMAP_ORIGIN . $path) ?></loc>
    <lastmod><?= x($lastmod) ?></lastmod>
    <changefreq><?= x($changefreq) ?></changefreq>
    <priority><?= x($priority) ?></priority>
  </url>
<?php endforeach; ?>

</urlset>
