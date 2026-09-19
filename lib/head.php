<?php
/**
 * Tech4TIME — the <head>, emitted once instead of pasted seventeen times.
 *
 * WHY THIS FILE EXISTS
 * Every page carried its own head: between 222 and 308 lines each, about 4,250
 * lines in all, and no propagation tool and no drift check over any of it.
 * tools/check_shared_markup.py held the header, footer and dock
 * byte-identical; the head was never in that set, and the template it came
 * from -- tools/templates/head.html, deleted with this file's arrival -- was
 * read exactly once per page, at birth, by tools/assemble_page.py. (The other
 * three went the same way afterwards, into lib/body.php. ADR 0023.)
 *
 * It drifted, exactly as that arrangement guarantees. The Organization graph
 * carried three office addresses and four telephone numbers as literal JSON in
 * sixteen of the seventeen heads -- the contact page alone rendered them from
 * content/contact.json -- so editing an office in the admin left sixteen pages
 * advertising the old one, and a build script -- tools/sync_site_contact.py,
 * since deleted -- was written to paste the new values back in before a
 * deploy. That whole mechanism is gone: the graph is built here, on the
 * request, from the document that owns the facts.
 *
 * WHAT A PAGE PASSES, AND WHY IT IS NOT A KEY
 * A page hands its OWN ADDRESS and its OWN meta band:
 *
 *     seo_head('/pages/about/', $data['meta'], ['pages/about.css']);
 *
 * The address rather than a key from SEO_ROUTES, because a service page's
 * address is a row's slug and is in no constant -- and because the canonical
 * is then the argument itself, which tools/audit_pages.py can check against
 * the directory the file actually sits in. A miscopied argument is the one
 * mistake an emitter makes possible, and that is the check that catches it.
 *
 * The meta band rather than a lookup, because the page has already loaded its
 * document to render its body. No second read, no second source.
 *
 * THE ONE PAGE WITH NO ADDRESS is the 404, which passes '' and seo_notfound().
 * It gets no canonical and no og:url, because it is served at every address
 * that does not exist and has no URL of its own to name.
 *
 * NOTHING HERE IS EDITABLE THAT SHOULD NOT BE. The canonical is derived from
 * the address and cannot be typed; the CSP, the favicon list, the font preload
 * and the stylesheet order are code. An editor able to break the Content
 * Security Policy is a hazard, not a feature.
 */

declare(strict_types=1);

require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/settings.php';

/**
 * The stylesheets every page loads, in cascade order, before its own.
 *
 * THE VERSION QUERY IS THE CACHE BUST AND IT IS NOT DECORATION. Filenames are
 * not content-hashed -- there is no build step to hash them -- and .htaccess
 * caches CSS for a year, so a changed stylesheet reaches nobody who has been
 * here before unless this string changes with it. It used to have to be bumped
 * in tools/templates/ and in all sixteen pages; it is bumped here now, once.
 * docs/20-deployment/routine-deploys.md, "Cache busting".
 */
const HEAD_STYLES = [
    'base.css',
    'theme.css?v=2',
    /* IMMEDIATELY AFTER theme.css AND NOWHERE ELSE. It is the same custom
       properties at the same specificity, so the later one wins -- and it
       holds only what a person changed, which is usually nothing at all. A
       host with no settings document, or one holding the palette it was seeded
       with, serves this as an EMPTY file. It is generated:
       assets/css/brand.css.php.

       ITS VERSION IS NOT WRITTEN HERE. Every other sheet is a file a developer
       edits, so its query is bumped by hand in the same breath. This one
       changes when somebody picks a colour, and nobody is here to bump
       anything -- so head_styles() appends the settings document's own
       revision, which contract_next_revision() already makes monotonic. */
    'brand.css',
    'layout.css?v=9',
    'components.css?v=1',
    'animations.css',
];

/**
 * The favicon set, in the order a browser reads it.
 *
 * Whole lines rather than an attribute table, because these are FILES and not
 * content: nothing here comes from a document, nothing is escaped, and the
 * only thing that could go wrong is naming a file that is not there --
 * tools/audit_pages.py resolves them. A table would also have to carry the
 * attribute ORDER to stay byte-identical with what these pages already send,
 * which is a lot of machinery for six constant lines.
 */
const HEAD_ICONS = [
    /*  name      the committed file, used until a square mark is uploaded  */
    'png16'  => '/assets/images/favicon/favicon-16.png',
    'png32'  => '/assets/images/favicon/favicon-32.png',
    'png48'  => '/assets/images/favicon/favicon-48.png',
    'png96'  => '/assets/images/favicon/favicon-96.png',
    'apple'  => '/assets/images/favicon/apple-touch-icon.png',
];

/**
 * The favicon set, in the order a browser reads it.
 *
 * SIX LINES THAT NAMED SIX COMMITTED FILES, and a company could not change
 * what its own browser tab shows without a developer. They are read from
 * content/settings.json now, each falling back to the file that ships, so a
 * host with no settings document sends exactly what it sent before.
 *
 * /favicon.ico is first and has no size of its own: it is the address a
 * browser probes blindly, before it has read a single line of the page. It is
 * assembled where it is served -- favicon.php -- which is why it is a path
 * here and not a generated one.
 */
function head_icons(array $settings): array
{
    $out = ['<link rel="icon" href="/favicon.ico" sizes="any">'];

    foreach (HEAD_ICONS as $name => $shipped) {
        $href = h(settings_icon($settings, $name, $shipped));

        $out[] = $name === 'apple'
            ? '<link rel="apple-touch-icon" sizes="180x180" href="' . $href . '">'
            : '<link rel="icon" type="image/png" sizes="'
              . SETTINGS_ICON_SIZES[$name]['size'] . 'x'
              . SETTINGS_ICON_SIZES[$name]['size'] . '" href="' . $href . '">';
    }

    return $out;
}

/**
 * Every stylesheet a page loads, in order, each with its cache-busting query.
 *
 * ONE OF THEM IS VERSIONED BY THE DOCUMENT AND NOT BY HAND. brand.css is
 * generated from content/settings.json, so what changes it is somebody picking
 * a colour rather than somebody editing a file -- and .htaccess caches
 * everything under assets/ for a year. Without a query that moves with the
 * palette, a returning visitor would keep last year's colours and the editor
 * would look broken to the only person who could see it.
 *
 * The revision is what moves: monotonic per document, minted by
 * contract_next_revision() on every save, and already what the publish channel
 * uses to decide which copy is newer.
 */
function head_styles(array $extra = []): array
{
    $out = [];

    foreach ([...HEAD_STYLES, ...$extra] as $sheet) {
        $out[] = $sheet === 'brand.css'
            ? 'brand.css?v=' . max(0, (int)(settings_load()['revision'] ?? 0))
            : $sheet;
    }

    return $out;
}

/**
 * The Content Security Policy, as defence in depth.
 *
 * NOTE: X-Frame-Options and X-Content-Type-Options are ignored in <meta> by
 * every browser -- they are set for real in .htaccess, which is the
 * authoritative source. Referrer-Policy and CSP genuinely do work here, and
 * are kept in case the host strips response headers.
 *
 * AND frame-ancestors IS IN THAT SAME CATEGORY, which this file argued for two
 * directives and then did not apply to a third. The CSP specification lists
 * frame-ancestors among the directives IGNORED when a policy is delivered by
 * <meta http-equiv>; only an HTTP header can carry it. So the copy that used
 * to be here protected nothing and read as though it did -- which is worse
 * than absent, because it invites somebody to conclude the page is safe to
 * frame-block without checking the header.
 *
 * Framing is refused for real by .htaccess, twice: the Content-Security-Policy
 * header carries frame-ancestors 'none' and X-Frame-Options: DENY is set
 * beside it. Nothing is weakened by dropping it here; a claim is.
 */
const HEAD_CSP = "default-src 'self'; img-src 'self' data:; style-src 'self'; "
               . "script-src 'self'; font-src 'self'; form-action 'self'; "
               . "base-uri 'self'; object-src 'none'";

/**
 * The same policy, widened by exactly what Google Analytics needs.
 *
 * THIS IS THE ONE PLACE THE "NO EXTERNAL ORIGIN" RULE IS BROKEN, and it is
 * broken only while somebody has put a measurement id in the editor. With the
 * field empty seo_head() sends HEAD_CSP above, unchanged, byte for byte -- so
 * the site's default state is the state it has always shipped in, and clearing
 * the field puts it back with a save rather than a deploy.
 *
 * .htaccess has to name these origins unconditionally, because a header cannot
 * read a JSON file. This policy is what actually keeps them shut: a browser
 * enforces every policy it is given, so the strict one above wins whenever it
 * is the one sent. See ADR 0021.
 */
const HEAD_CSP_ANALYTICS =
      "default-src 'self'; "
    . "img-src 'self' data: https://*.google-analytics.com https://*.googletagmanager.com; "
    . "style-src 'self'; "
    . "script-src 'self' https://www.googletagmanager.com; "
    . "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com "
    . "https://*.googletagmanager.com; "
    . "font-src 'self'; form-action 'self'; "
    . "base-uri 'self'; object-src 'none'";

/**
 * The encoding every JSON-LD block on this site uses.
 *
 * JSON_HEX_TAG IS PART OF IT, AND IS THE ONE THAT IS NOT COSMETIC. Every string
 * in these blocks is editable from the admin, and a stored value containing
 * "</script>" would close the block it sits inside — turning the rest of the
 * graph into markup and whatever followed into something a browser parses.
 * services_json_ld() set it and said in its own docblock that the other
 * emitters should too; that note sat there while six of the seven did not.
 * It costs nothing today: no value in any document contains a < or a >, so the
 * output is byte for byte what the pages already carried.
 *
 * The other three are readability: pretty-printed, with slashes and non-ASCII
 * left alone, because an escaped block is unreadable in View Source and this is
 * the one part of the page a person checks by eye against a validator.
 *
 * Referenced from lib/services.php and lib/contact.php as well. Safe from both:
 * a constant inside a function body is resolved when the function RUNS, and
 * every page loads lib/head.php before it renders a line.
 */
const HEAD_JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                      | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;

/**
 * Everything between <head> and the page's own structured data.
 *
 * $route   this page's address, with its trailing slash. '' for the 404.
 * $meta    this page's meta band, out of its own content document.
 * $styles  its own stylesheets, as paths under /assets/css/.
 */
function seo_head(string $route, array $meta, array $styles = [],
                  string $updated = ''): void
{
    $site  = seo_site();
    $share = seo_share($meta);
    $url   = seo_url($route);

    $title       = (string)($meta['title'] ?? '');
    $description = (string)($meta['description'] ?? '');
    $keywords    = (string)($meta['keywords'] ?? '');
    $analytics   = (string)(seo_crawl()['analytics_id'] ?? '');
    $shareTitle  = (string)($meta['share_title'] ?? '') !== ''
        ? (string)$meta['share_title'] : $title;
    $robots      = (string)($meta['robots'] ?? 'index');

    $out = [];
    $out[] = '<meta charset="utf-8">';
    $out[] = '<meta name="viewport" content="width=device-width, initial-scale=1">';
    $out[] = '';
    $out[] = '<title>' . h($title) . '</title>';
    $out[] = '<meta name="description" content="' . h($description) . '">';

    /* KEYWORDS, AND WHAT THEY ARE AND ARE NOT WORTH. Google has ignored this
       tag since 2009 and says so in public; Bing treats it as a spam signal
       when it is stuffed. It is emitted because it is asked for, it is honest
       when it is short and true of the page, and several smaller and regional
       engines plus on-site search tools still read it. It is omitted entirely
       when empty rather than sent blank, so a page that has not been given a
       list makes no claim at all. */
    if ($keywords !== '') {
        $out[] = '<meta name="keywords" content="' . h($keywords) . '">';
    }

    /* A canonical is a claim that this address is the right one for this page.
       The 404 is served at every address that does not exist, so it has no
       such claim to make and must not be given one. */
    if ($url !== '') {
        $out[] = '<link rel="canonical" href="' . h($url) . '">';
    }

    $out[] = '';
    $out[] = $robots === 'noindex'
        ? "<!-- An error page must never be indexed, but its links should still be\n"
        . "     followed so crawl equity flows back into the site. -->"
        : "<!-- Crawling. Large image previews and full snippets are allowed so rich\n"
        . "     results can use the branded share card. -->";
    $out[] = '<meta name="robots" content="' . h(seo_robots_directive($robots)) . '">';

    /* The two tags that let somebody claim this site in a search engine's
       console. Emitted only when there is a token: a verification tag naming
       nobody is noise in every head on the site. Without one of these there is
       no Search Console, and no way to see a query the site ranks for, a page
       that was refused indexing, or a structured-data error. */
    $verify = [
        'google-site-verification' => (string)(seo_load()['crawl']['verify_google'] ?? ''),
        'msvalidate.01'            => (string)(seo_load()['crawl']['verify_bing'] ?? ''),
    ];
    $verify = array_filter($verify, static fn(string $v): bool => trim($v) !== '');
    if ($verify !== []) {
        $out[] = '';
        $out[] = '<!-- Site ownership, for the search consoles. -->';
        foreach ($verify as $name => $token) {
            $out[] = '<meta name="' . h($name) . '" content="' . h(trim($token)) . '">';
        }
    }

    $out[] = '';
    $out[] = "<!-- Security. NOTE: X-Frame-Options and X-Content-Type-Options are ignored in\n"
           . "     <meta> by every browser — they are set for real in .htaccess, which is the\n"
           . "     authoritative source. Referrer-Policy and CSP genuinely do work here, and\n"
           . "     are kept as defence in depth in case the host strips response headers. -->";
    $out[] = '<meta name="referrer" content="strict-origin-when-cross-origin">';
    /* NOT through h(). The policy is a constant in this file, contains no
       stored value, and its apostrophes are syntax: htmlspecialchars would
       turn 'self' into &#039;self&#039; and the browser would refuse the whole
       policy. The rule that everything editable goes through h() is intact --
       nothing here is editable. */
    $out[] = '<meta http-equiv="Content-Security-Policy" content="'
           . ($analytics !== '' ? HEAD_CSP_ANALYTICS : HEAD_CSP) . '">';

    $out[] = '';
    $out[] = '<!-- Open Graph -->';
    $out[] = '<meta property="og:type" content="' . h($site['og_type']) . '">';
    $out[] = '<meta property="og:locale" content="' . h($site['locale']) . '">';
    $out[] = '<meta property="og:site_name" content="' . h($site['name']) . '">';
    $out[] = '<meta property="og:title" content="' . h($shareTitle) . '">';
    $out[] = '<meta property="og:description" content="' . h($description) . '">';
    if ($url !== '') {
        $out[] = '<meta property="og:url" content="' . h($url) . '">';
    }
    /* WHEN THE PAGE LAST CHANGED, WHICH THE SITE HAS NEVER SAID. Every
       document has carried an `updated` stamp since api/publish.php started
       setting it, and every page threw it away. Freshness is a real input, and
       a page that says when it changed is a page a crawler can decide to
       revisit; one that says nothing has to be guessed at. Emitted only when
       there is a stamp -- a page that has never been published makes no
       claim, the same rule the sitemap's lastmod follows. */
    $day = seo_stamp($updated);
    if ($day !== '') {
        $out[] = '<meta property="og:updated_time" content="' . h($day) . '">';
    }
    if ($share !== []) {
        $out[] = '<meta property="og:image" content="' . h($share['url']) . '">';
        $out[] = '<meta property="og:image:width" content="' . h((string)$share['width']) . '">';
        $out[] = '<meta property="og:image:height" content="' . h((string)$share['height']) . '">';
        $out[] = '<meta property="og:image:alt" content="' . h($share['alt']) . '">';
    }

    $out[] = '';
    $out[] = '<!-- Twitter -->';
    $out[] = '<meta name="twitter:card" content="' . h($site['twitter_card']) . '">';
    $out[] = '<meta name="twitter:title" content="' . h($shareTitle) . '">';
    $out[] = '<meta name="twitter:description" content="' . h($description) . '">';
    if ($share !== []) {
        $out[] = '<meta name="twitter:image" content="' . h($share['url']) . '">';
        $out[] = '<meta name="twitter:image:alt" content="' . h($share['alt']) . '">';
    }

    $out[] = '';
    $out[] = '<!-- Icons -->';
    foreach (head_icons(settings_load()) as $icon) {
        $out[] = $icon;
    }
    $out[] = '<link rel="manifest" href="/site.webmanifest">';
    $out[] = '<meta name="theme-color" media="(prefers-color-scheme: light)" content="'
           . h($site['theme_light']) . '">';
    $out[] = '<meta name="theme-color" media="(prefers-color-scheme: dark)" content="'
           . h($site['theme_dark']) . '">';

    $out[] = '';
    $out[] = "<!-- Fonts. Preloaded because the latin subset is on the critical render path;\n"
           . "     the -ext subset is not preloaded since most pages never reference it. -->";
    $out[] = '<link rel="preload" href="/assets/fonts/inter-latin.woff2" as="font" '
           . 'type="font/woff2" crossorigin>';

    $out[] = '';
    $out[] = "<!-- Styles, in cascade order.\n"
           . "\n"
           . "     THE VERSION QUERY IS THE CACHE BUST, AND IT IS NOT DECORATION\n"
           . "     Filenames are not content-hashed — there is no build step to hash them —\n"
           . "     and .htaccess caches CSS for a year. A changed stylesheet does not reach\n"
           . "     anybody who has been here before unless this string changes with it, so\n"
           . "     bump it in the same breath as the file. Forget, and the release is for\n"
           . "     new visitors only, which looks like nothing at all from here.\n"
           . "     docs/20-deployment/routine-deploys.md, \"Cache busting\" -->";
    foreach (head_styles($styles) as $sheet) {
        $out[] = '<link rel="stylesheet" href="/assets/css/' . h($sheet) . '">';
    }

    $out[] = '';
    $out[] = "<!-- Colour mode, applied before first paint to avoid a flash of the wrong\n"
           . "     theme. Deliberately NOT deferred; see the comment in the file itself. -->";
    $out[] = '<script src="/assets/js/theme-init.js"></script>';

    /* MEASUREMENT, AND ONLY IF ASKED FOR. Two tags rather than the one Google
       documents, because the second half of their snippet is an inline script
       and script-src 'self' refuses those silently -- the page would look
       right and measure nothing. analytics.js is this site's own file and
       reads the id off its own data attribute.

       The id has already been through SEO_ANALYTICS_ID in contract_normalise(),
       so what reaches this line is letters, digits and dashes or nothing at
       all; h() is still applied, because "it cannot get here" is a reason to
       check, not a reason to skip. */
    if ($analytics !== '') {
        $out[] = '';
        $out[] = '<!-- Google Analytics. Emitted only while a measurement id is set on'
               . "\n     the SEO screen; clearing it removes these two lines and closes"
               . "\n     the Content Security Policy above again. ADR 0021. -->";
        $out[] = '<script async src="https://www.googletagmanager.com/gtag/js?id='
               . h(rawurlencode($analytics)) . '"></script>';
        $out[] = '<script src="/assets/js/analytics.js?v=1" data-ga="'
               . h($analytics) . '" defer></script>';
    }

    echo implode("\n", $out), "\n";
}

/* ------------------------------------------------------ structured data */

/**
 * The graph every page carries: who this is, what the site is, what it sells.
 *
 * The addresses and telephone numbers come from content/contact.json through
 * contact_addresses() and contact_points(), which the contact page has always
 * used and the other sixteen pages never did. That is the whole of the fix for
 * a graph that went stale sixteen pages at a time.
 */
function seo_graph(): array
{
    $site     = seo_site();
    $identity = seo_identity();
    $contact  = contact_load();
    $home     = seo_url('/');
    $share    = seo_share([]);

    $organization = [
        '@type'         => 'Organization',
        '@id'           => SEO_ORIGIN . '/#organization',
        'name'          => $site['name'],
        // THE FIELD THAT WENT NOWHERE. identity.legal_name is offered on
        // ?s=seo&site=identity as "Organisation name", marked required, refused
        // when empty by seo_validate() and round-tripped by test_publish.py --
        // and until now it was read by nothing at all. Somebody typed the
        // registered name of the company into a required field and it reached
        // no page. schema.org's Organization.legalName is precisely this, and
        // it is what tells a search engine that the trading name above and the
        // entity on the paperwork are one company.
        'legalName'     => (string)$identity['legal_name'],
        'alternateName' => $identity['alternate_name'],
        'url'           => $home,
        'logo'          => [
            '@type'  => 'ImageObject',
            'url'    => seo_url((string)$identity['logo']['src']),
            'width'  => (int)$identity['logo']['width'],
            'height' => (int)$identity['logo']['height'],
        ],
        'image'         => $share['url'] ?? '',
        'description'   => $identity['description'],
        'slogan'        => $identity['slogan'],
        'foundingDate'  => $identity['founded'],
        'email'         => contact_email($contact),
        'areaServed'    => $identity['area_served'],
        'knowsLanguage' => $site['lang'],
        'address'       => contact_addresses($contact),
        'contactPoint'  => contact_points($contact),
        'sameAs'        => array_values(array_map(
            static fn(array $row): string => (string)$row['url'],
            array_filter(seo_shown(seo_load(), 'sameas'),
                         static fn(array $row): bool => trim((string)$row['url']) !== '')
        )),
    ];

    /* An empty legalName is worse than none: it is the graph asserting that the
       company has no registered name. The editor refuses to save one, so this
       only fires for a document written before the field existed, or by hand. */
    if (trim($organization['legalName']) === '') {
        unset($organization['legalName']);
    }

    $website = [
        '@type'       => 'WebSite',
        '@id'         => SEO_ORIGIN . '/#website',
        'url'         => $home,
        'name'        => $site['name'],
        'description' => $site['description'],
        'publisher'   => ['@id' => SEO_ORIGIN . '/#organization'],
        'inLanguage'  => $site['lang'],
    ];

    $service = [
        '@type'                    => 'ProfessionalService',
        '@id'                      => SEO_ORIGIN . '/#service',
        'name'                     => $site['name'],
        'url'                      => $home,
        'image'                    => $share['url'] ?? '',
        'parentOrganization'       => ['@id' => SEO_ORIGIN . '/#organization'],
        'priceRange'               => $identity['price_range'],
        'areaServed'               => $identity['area_served'],
        'openingHoursSpecification' => array_values(array_map(
            static fn(array $row): array => array_filter([
                '@type'       => 'OpeningHoursSpecification',
                'dayOfWeek'   => $row['days'],
                'opens'       => $row['opens'],
                'closes'      => $row['closes'],
                'description' => $row['label'],
            ], static fn($v): bool => $v !== '' && $v !== []),
            array_filter(seo_shown(seo_load(), 'hours'),
                         static fn(array $row): bool => $row['days'] !== [])
        )),
        'serviceType'              => $identity['service_types'],
        'knowsAbout'               => $identity['knows_about'],
    ];

    $graph = [];
    foreach ([$organization, $website, $service, ...seo_offices($contact)] as $node) {
        $graph[] = array_filter(
            $node,
            static fn($v): bool => $v !== '' && $v !== [] && $v !== null
        );
    }

    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

/**
 * One LocalBusiness node per office, which this site has never had either.
 *
 * The three offices existed only as PostalAddress entries inside the
 * Organization -- correct, and not what a local result reads. A local pack is
 * built from places, and a place needs its own @id, its own address, its own
 * telephone and its own hours. Everything below already exists in
 * content/contact.json and is already on the contact page; nothing here is a
 * new fact, only a shape a search engine has a definition for.
 *
 * The hours are matched to an office by the label somebody typed on the SEO
 * screen -- "Bangladesh office" against the office named "Bangladesh". A row
 * that matches nothing is left off that office rather than attached to all of
 * them, because opening hours on the wrong continent are worse than none.
 */
function seo_offices(array $contact): array
{
    $hours = seo_shown(seo_load(), 'hours');
    $share = seo_share([]);        /* the site-wide card; see the note below */
    $out   = [];

    foreach (contact_shown_offices($contact) as $office) {
        $name    = trim((string)$office['name']);
        $schema  = $office['schema'];
        $country = strtoupper(trim((string)$schema['country']));

        $address = array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => trim((string)$schema['street']),
            'addressLocality' => trim((string)$schema['locality']),
            'addressRegion'   => trim((string)$schema['region']),
            'postalCode'      => trim((string)$schema['postal_code']),
            'addressCountry'  => $country,
        ], static fn(string $v): bool => $v !== '');

        /* A country on its own is not a place anybody can visit. Same test
           contact_addresses() applies, and for the same reason. */
        if ($name === '' || count($address) <= 2) {
            continue;
        }

        $node = [
            '@type'              => 'LocalBusiness',
            '@id'                => SEO_ORIGIN . '/#office-' . rawurlencode(strtolower($name)),
            'name'               => seo_site()['name'] . ' — ' . $name,
            'parentOrganization' => ['@id' => SEO_ORIGIN . '/#organization'],
            'url'                => seo_url('/pages/contact/'),
            'address'            => $address,
            'telephone'          => array_values(array_map(
                static fn($phone): string => contact_tel((string)$phone),
                $office['phones']
            )),
            'email'              => contact_email($contact),
            'priceRange'         => seo_identity()['price_range'],
            /* THE SHARE CARD, WHICH IS WHAT Organization ALSO USES, and
               deliberately NOT the office's own picture. That field is a flag:
               it overrides the shipped country slug, CONTRACT_IMAGE_SLOTS
               stores it at 56px because 56px is where it is drawn, and a
               56-pixel flag offered to a search engine as the photograph of a
               place of business is a worse answer than none at all.

               A real photograph per office would be a different field at a
               different width. Until there is one, each office carries the
               card the rest of the site carries, which is at least accurate.

               Set unconditionally, like 'email' and 'priceRange' above:
               seo_graph() array_filter()s every node it assembles, so an empty
               card drops the key rather than shipping "image": "". */
            'image'              => $share['url'] ?? '',
        ];

        /* BOTH OR NEITHER. A GeoCoordinates node with one number in it is not
           half a pin, it is a pin somewhere on a line of longitude through the
           middle of the planet -- so a half-filled pair is dropped entirely
           rather than published as far as it goes.

           contact_coordinate() has already turned anything that is not a
           coordinate into '', so this is the only test needed here. */
        $lat = (string)($schema['latitude'] ?? '');
        $lon = (string)($schema['longitude'] ?? '');

        if ($lat !== '' && $lon !== '') {
            $node['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $lat,
                'longitude' => $lon,
            ];
        }

        /* The rule lives in the contract, because the editor asks it too --
           it is what tells an operator which office has no hours attached. */
        $matched = seo_hours_for_office($hours, $name);

        if ($matched !== []) {
            $node['openingHoursSpecification'] = array_map(
                static fn(array $row): array => array_filter([
                    '@type'     => 'OpeningHoursSpecification',
                    'dayOfWeek' => $row['days'],
                    'opens'     => $row['opens'],
                    'closes'    => $row['closes'],
                ], static fn($v): bool => $v !== '' && $v !== []),
                $matched
            );
        }

        $out[] = $node;
    }

    return $out;
}

/**
 * The base graph, and this page's trail.
 *
 * A page's own schema -- Service, JobPosting, ContactPage, AboutPage -- is
 * generated by that page from its own document and printed after this.
 */
function seo_jsonld(string $route, array $meta, string $updated = ''): void
{
    echo "<!-- Who this is, what the site is, and what it sells. Identical on every\n",
         "     page, and generated rather than pasted: the addresses and telephone\n",
         "     numbers come from content/contact.json, so editing an office in the\n",
         "     admin changes every page at once. Per-page schema goes after it. -->\n";
    echo '<script type="application/ld+json">', "\n";
    echo json_encode(seo_graph(), HEAD_JSON_FLAGS), "\n";
    echo '</script>', "\n";

    $crumbs = seo_breadcrumb($route, (string)($meta['breadcrumb'] ?? ''));

    if ($crumbs !== []) {
        echo "\n<!-- Where this page sits, built from its address rather than numbered by\n",
             "     hand. See seo_breadcrumb(). -->\n";
        echo '<script type="application/ld+json">', "\n";
        echo json_encode([
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        ], HEAD_JSON_FLAGS), "\n";
        echo '</script>', "\n";
    }

    $page = seo_page_node($route, $meta, $updated, $crumbs);
    if ($page === []) {
        return;
    }

    echo "\n<!-- The page itself, as a node of the graph above. Without it each block\n",
         "     on the page is an island: this is what says the trail, the share card\n",
         "     and the site all belong to THIS address, and what carries the date the\n",
         "     page last changed. -->\n";
    echo '<script type="application/ld+json">', "\n";
    echo json_encode($page, HEAD_JSON_FLAGS), "\n";
    echo '</script>', "\n";
}

/**
 * The WebPage node, which this site has never had.
 *
 * It ties the four things a crawler otherwise has to guess are related: this
 * URL, the WebSite it is part of, the trail that leads to it and the picture
 * that represents it -- plus dateModified, which is the freshness signal every
 * document has carried and no page has ever emitted.
 *
 * Returns [] for a page with no address. The 404 is served everywhere and is
 * a page about nothing; a WebPage node for it would name a URL it does not
 * have.
 */
function seo_page_node(string $route, array $meta, string $updated, array $crumbs): array
{
    if ($route === '') {
        return [];
    }

    $url   = seo_url($route);
    $share = seo_share($meta);
    $day   = seo_stamp($updated);

    $node = [
        '@context'    => 'https://schema.org',
        '@type'       => 'WebPage',
        '@id'         => $url . '#webpage',
        'url'         => $url,
        'name'        => (string)($meta['title'] ?? ''),
        'description' => (string)($meta['description'] ?? ''),
        'isPartOf'    => ['@id' => SEO_ORIGIN . '/#website'],
        'about'       => ['@id' => SEO_ORIGIN . '/#organization'],
        'inLanguage'  => seo_site()['lang'],
    ];

    if ($day !== '') {
        $node['dateModified'] = $day;
    }
    if ($share !== []) {
        $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $share['url']];
    }
    if ($crumbs !== []) {
        $node['breadcrumb'] = [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $crumbs,
        ];
    }

    return $node;
}

/**
 * A publish stamp as a date, or '' when there has never been one.
 *
 * Same rule as seo_document_day(): a page that has never been published says
 * nothing about when it changed, rather than saying today.
 */
function seo_stamp(string $updated): string
{
    $day = substr(trim($updated), 0, 10);

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? $day : '';
}
