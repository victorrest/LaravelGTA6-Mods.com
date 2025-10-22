<?php
if (!defined('ABSPATH')) {
    exit;
}

if (post_password_required()) {
    return;
}

$payload = function_exists('gta6mods_build_comments_payload')
    ? gta6mods_build_comments_payload(
        get_the_ID(),
        [
            'orderby'  => 'best',
            'page'     => 1,
            'per_page' => 15,
        ]
    )
    : null;

if (is_array($payload) && isset($payload['html']) && '' !== $payload['html']) {
    echo $payload['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return;
}
?>
<div id="gta6-comments"></div>
<?php
