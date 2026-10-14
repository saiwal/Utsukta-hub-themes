<?php

// Drop SabreDAV's hardcoded CSP from /cloud page renders.
//
// Any browser GET to a DAV collection passes through
// Sabre\DAV\Browser\Plugin::httpGet(), which sets
//   Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self'; font-src 'self';
// (vendor/sabre/dav/lib/DAV/Browser/Plugin.php). It names no script-src, so the
// default-src fallback blocks this theme's own SPA bundle and a shared,
// bookmarked or reloaded /cloud link renders a blank page.
//
// This file is included by construct_page() (boot.php) and nowhere else, so the
// removal is confined to page renders — a *file* GET under /cloud never gets
// here and keeps SabreDAV's policy, which still matters there: it is what
// sandboxes an inline-served upload (an SVG, or an .html from a channel with
// the 'code' permission, which Storage/File.php then does not force to
// text/plain). Only that exact policy is dropped; core's own CSP is written
// later in construct_page() and is unaffected.
foreach (headers_list() as $solidified_hdr) {
	if (stripos($solidified_hdr, 'content-security-policy:') === 0
		&& strpos($solidified_hdr, "default-src 'none'") !== false) {
		header_remove('Content-Security-Policy');
		break;
	}
}
unset($solidified_hdr);

// Allow blob: frames under core's opt-in CSP (system.content_security_policy).
// FilePreviewModal shows PDFs in an <iframe src="blob:…"> (a blob carries no
// Content-Disposition, so the browser renders instead of downloading), and
// core's default frame-src 'self' doesn't cover blob:. Inserted per request
// rather than register_hook()ed so existing hubs need no theme re-enable.
\Zotlabs\Extend\Hook::insert('content_security_policy', function (&$csp) {
	$csp['frame-src'][] = 'blob:';
});
