<?php
/**
 * Template Name: Mod Update
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$current_user_id = get_current_user_id();
$mod_id = isset($_GET['mod_id']) ? absint($_GET['mod_id']) : 0;
$mod_post = $mod_id > 0 ? get_post($mod_id) : null;
$allowed_types = function_exists('gta6mods_get_mod_post_types') ? gta6mods_get_mod_post_types() : ['post'];

if (!$mod_post instanceof WP_Post || !in_array($mod_post->post_type, $allowed_types, true)) {
    echo '<main class="container mx-auto p-4 lg:p-6"><div class="card p-6"><h2 class="text-2xl font-bold text-gray-900">' . esc_html__('The mod you want to update could not be found.', 'gta6-mods') . '</h2><p class="mt-2 text-gray-600">' . esc_html__('Please return to the mod list and select the file again.', 'gta6-mods') . '</p></div></main>';
    get_footer();
    return;
}

if (!is_user_logged_in()) {
    echo '<main class="container mx-auto p-4 lg:p-6"><div class="card p-6"><h2 class="text-2xl font-bold text-gray-900">' . esc_html__('Sign in required', 'gta6-mods') . '</h2><p class="mt-2 text-gray-600">' . esc_html__('Please sign in to your account before submitting an update.', 'gta6-mods') . '</p></div></main>';
    get_footer();
    return;
}

if (!current_user_can('edit_post', $mod_id)) {
    echo '<main class="container mx-auto p-4 lg:p-6"><div class="card p-6"><h2 class="text-2xl font-bold text-gray-900">' . esc_html__('You do not have permission to update this mod.', 'gta6-mods') . '</h2><p class="mt-2 text-gray-600">' . esc_html__('Only the author or users with edit permissions can submit updates.', 'gta6-mods') . '</p></div></main>';
    get_footer();
    return;
}

$is_mod_pending = ('pending' === $mod_post->post_status);
$is_author_view = ((int) $mod_post->post_author === $current_user_id);

if ($is_mod_pending && $is_author_view && !current_user_can('edit_others_posts')) {
    echo '<main class="container mx-auto p-4 lg:p-6"><div class="card p-6 md:p-8"><div class="bg-sky-50 border border-sky-200 text-sky-900 rounded-xl p-6"><h2 class="text-2xl font-bold mb-2">' . esc_html__('This mod is still pending review', 'gta6-mods') . '</h2><p class="text-base">' . esc_html__('Thanks for uploading! Your submission is waiting for the moderation team to approve it. As soon as they finish the review it will be published automatically, and you can submit updates afterwards.', 'gta6-mods') . '</p></div></div></main>';
    get_footer();
    return;
}

$has_pending_update = gta6mods_mod_has_pending_update($mod_id);
$can_bypass_pending = gta6mods_user_can_bypass_pending_lock($current_user_id);

if ($has_pending_update && !$can_bypass_pending) {
    echo '<main class="container mx-auto p-4 lg:p-6"><div class="card p-6 md:p-8"><div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6"><h2 class="text-2xl font-bold mb-2">' . esc_html__('Update already pending review', 'gta6-mods') . '</h2><p class="text-base">' . esc_html__('This mod already has an update waiting for moderator review. Please wait for the current request to be approved or rejected before submitting another one.', 'gta6-mods') . '</p></div></div></main>';
    get_footer();
    return;
}

$update_data = [
    'modId'            => $mod_id,
    'modTitle'         => get_the_title($mod_id),
    'restBase'         => esc_url_raw(rest_url('gta6-mods/v1')),
    'modDataUrl'       => esc_url_raw(rest_url('gta6-mods/v1/mod/' . $mod_id . '/data')),
    'restNonce'        => wp_create_nonce('wp_rest'),
    'ajaxUrl'          => admin_url('admin-ajax.php'),
    'nonce'            => wp_create_nonce('gta6mods_update_mod'),
    'downloadIncrementUrl' => esc_url_raw(rest_url('gta6mods/v1/mod/' . $mod_id . '/download')),
    'trackingNonce'    => wp_create_nonce('gta6mods_tracking'),
    'modPermalink'     => get_permalink($mod_id),
    'labels'           => [
        'authorPlaceholder' => __('Enter co-author name', 'gta6-mods'),
        'changelogEmpty'    => __('No changes added yet.', 'gta6-mods'),
        'noScreenshots'     => __('No screenshots to display yet.', 'gta6-mods'),
        'download'          => __('Download', 'gta6-mods'),
        'currentBadge'      => __('Current', 'gta6-mods'),
        'initialBadge'      => __('Initial Release', 'gta6-mods'),
        'genericError'      => __('An unexpected error occurred. Please try again.', 'gta6-mods'),
        'titleRequired'     => __('Please provide a mod title.', 'gta6-mods'),
        'categoryRequired'  => __('Please select a category.', 'gta6-mods'),
        'removeFile'        => __('Remove', 'gta6-mods'),
        'submitting'        => __('Submitting…', 'gta6-mods'),
        'submitted'         => __('Submitted', 'gta6-mods'),
        'redirecting'       => __('Redirecting…', 'gta6-mods'),
        'virusScan'         => __('Virus Scan', 'gta6-mods'),
        'loadingModData'    => __('Loading mod data…', 'gta6-mods'),
        'loadError'         => __('We could not load the mod details. Please try again.', 'gta6-mods'),
        'retry'             => __('Retry', 'gta6-mods'),
    ],
    'text'             => [
        'editBasicInformation'    => __('Edit Basic Information', 'gta6-mods'),
        'fileNameLabel'           => __('File Name', 'gta6-mods'),
        'categoryLabel'           => __('Category', 'gta6-mods'),
        'authorsLabel'            => __('Author(s)', 'gta6-mods'),
        'addAuthor'               => __('Add Author', 'gta6-mods'),
        'tagsLabel'               => __('Tags', 'gta6-mods'),
        'tagsPlaceholder'         => __('e.g. car, addon, tuning', 'gta6-mods'),
        'descriptionLabel'        => __('Description', 'gta6-mods'),
        'descriptionHelper'       => __('Use the formatting toolbar to describe your mod and provide installation notes.', 'gta6-mods'),
        'manageScreenshots'       => __('Manage Screenshots', 'gta6-mods'),
        'clickToUpload'           => __('Click to upload', 'gta6-mods'),
        'orDragAndDrop'           => __('or drag and drop', 'gta6-mods'),
        'screenshotFileTypes'     => __('JPG, PNG, WEBP (max. 10MB)', 'gta6-mods'),
        'screenshotNote'          => __('The first image becomes the featured image. Drag to reorder.', 'gta6-mods'),
        'fileSettings'            => __('File Settings', 'gta6-mods'),
        'videoPermissionsTitle'   => __('Video Upload Permissions', 'gta6-mods'),
        'videoDeny'               => __('Deny', 'gta6-mods'),
        'videoModerate'           => __('Self moderate', 'gta6-mods'),
        'videoAllow'              => __('Allow', 'gta6-mods'),
        'totalStats'              => __('Total Stats', 'gta6-mods'),
        'likes'                   => __('Likes', 'gta6-mods'),
        'views'                   => __('Views', 'gta6-mods'),
        'downloads'               => __('Downloads', 'gta6-mods'),
        'versionsTitle'           => __('Version(s)', 'gta6-mods'),
        'versionsSubtitle'        => __('You can only upload new versions. Existing files cannot be removed or modified.', 'gta6-mods'),
        'uploadNewVersion'        => __('Upload a New Version', 'gta6-mods'),
        'newVersionNumber'        => __('New Version Number', 'gta6-mods'),
        'newVersionPlaceholder'   => __('e.g. 2.2.0', 'gta6-mods'),
        'newVersionHelper'        => __('The new version number must be higher than the current one.', 'gta6-mods'),
        'newModFile'              => __('New Mod File', 'gta6-mods'),
        'orProvideDownloadLink'   => __('Or provide a download link', 'gta6-mods'),
        'allowedFileTypes'        => __('Allowed: .zip, .rar, .7z, .oiv (max. 400MB)', 'gta6-mods'),
        'downloadUrlLabel'        => __('Download URL', 'gta6-mods'),
        'orUploadFile'            => __('Or upload a file', 'gta6-mods'),
        'downloadUrlPlaceholder'  => __('https://...', 'gta6-mods'),
        'fileSizeLabel'           => __('File Size', 'gta6-mods'),
        'changelogLabel'          => __('Changelog (What changed in this version?)', 'gta6-mods'),
        'changelogPlaceholder'    => __('e.g. Fixed handling issues', 'gta6-mods'),
        'addButton'               => __('Add', 'gta6-mods'),
        'changelogHelper'         => __('Add each change as a separate entry.', 'gta6-mods'),
        'cancel'                  => __('Cancel', 'gta6-mods'),
        'submitUpdate'            => __('Submit Update', 'gta6-mods'),
    ],
];

wp_enqueue_script('gta6-mods-update');
wp_localize_script('gta6-mods-update', 'GTAModsUpdatePage', $update_data);
?>
<style>
    .card { background-color: #fff; border-radius: 0.75rem; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 1%), 0 2px 4px -2px rgb(0 0 0 / 4%); }
    .btn-action { background-color: #ec4899; color: #fff; transition: all .3s ease; box-shadow: 0 4px 14px 0 rgba(236, 72, 153, .30); }
    .btn-action:hover { background-color: #db2777; }
    .btn-secondary { background-color: #e5e7eb; color: #374151; transition: background-color .3s ease; }
    .btn-secondary:hover { background-color: #d1d5db; }
    .category-active { opacity: 1 !important; transform: scale(1.05); text-shadow: 0 0 8px rgba(255, 255, 255, 0.7); }
    .category-dimmed { opacity: 0.45 !important; }
    .form-input, .form-select, .form-textarea, .form-url { width: 100%; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.75rem 1rem; transition: all .2s ease-in-out; }
    .form-input:focus, .form-select:focus, .form-textarea:focus, .form-url:focus { outline: 0; border-color: #ec4899; box-shadow: 0 0 0 2px #fbcfe8; }
    .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
    .info-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1rem; }
    summary::-webkit-details-marker { display: none; }
    details[open] > summary { border-style: solid; border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
    .locked-field { background-color: #f3f4f6; cursor: not-allowed; opacity: 0.7; }
    #editorjs-container {
        padding: 1rem;
        min-height: 264px;
        background-color: #fff;
        border-radius: 0.5rem;
        border: 1px solid #d1d5db;
        transition: all 0.2s ease-in-out;
        cursor: text;
        touch-action: manipulation;
    }
    #editorjs-container:focus-within {
        border-color: #ec4899;
        box-shadow: 0 0 0 2px #fbcfe8;
    }
    #editorjs-container .ce-paragraph,
    #editorjs-container .ce-header,
    #editorjs-container .cdx-list__item {
        color: #1f2937;
    }
    .codex-editor__redactor {
        padding-bottom: 100px !important;
    }
    .codex-editor {
        width: 100%;
    }
    .ce-block__content,
    .ce-toolbar__content {
        max-width: none;
    }
    .ce-toolbar__actions {
        right: 0;
    }
    #editorjs-container .ce-header {
        font-weight: 800;
        padding: 0;
        margin-bottom: 0.55em;
        margin-top: .75em;
    }
    #editorjs-container h2 { font-size: 1.5em; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5em; }
    #editorjs-container h3 { font-size: 1.25em; color: #be185d; }
    #editorjs-container h4 { font-size: 1.1em; font-weight: 700; }

    #editorjs-container blockquote {
        border-left: 5px solid #ec4899;
        background-color: #f9fafb;
        padding: 1em 1.5em;
        font-style: italic;
        margin: 1.5em 0;
        border-radius: 0 0.5rem 0.5rem 0;
        color: #4b5563;
    }
    #editorjs-container blockquote .ce-blockquote__caption {
        color: #86198f;
        font-weight: 600;
        font-style: normal;
        margin-top: 0.5em;
        text-align: right;
    }

    #editorjs-container .ce-popover--opened {
        background-color: #f9fafb;
        color: white;
        border-radius: 6px;
    }
    .cdx-search-field__input{
        color: #000;
    }
    .ce-inline-toolbar__dropdown:hover{
        background: #364050!important;
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
    }
    .ce-conversion-toolbar__label {
        color: #2d3341;
    }
    .ce-conversion-toolbar__tools {
        color: #141e2c;
    }
    #editorjs-container .ce-popover__item:hover {
        background-color: #374151 !important;
    }
    #editorjs-container .ce-popover__item-icon, #editorjs-container .ce-popover__item-label {
        color: white !important;
    }

    #editorjs-container .cdx-list {
        margin-top: .5em;
        margin-bottom: .5em;
        padding-left: 1.75rem;
    }
    #editorjs-container .cdx-list--ordered {
         list-style-type: decimal;
    }
    #editorjs-container .cdx-list--unordered {
        list-style-type: disc;
    }
    #editorjs-container .cdx-list__item {
        padding: 0.25rem 0;
    }
    .gta6-editor-embed,
    #editorjs-container .gta6-youtube-tool .gta6-youtube-preview {
        position: relative;
        width: 100%;
        padding-bottom: 56.25%;
        border-radius: 0.75rem;
        overflow: hidden;
        background-color: #000;
    }
    .gta6-editor-embed iframe,
    #editorjs-container .gta6-youtube-tool .gta6-youtube-preview iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: 0;
    }
    #editorjs-container .gta6-youtube-tool {
        display: grid;
        gap: 0.75rem;
        padding: 0.25rem 0;
    }
    #editorjs-container .gta6-youtube-tool label {
        font-weight: 600;
        font-size: 0.9rem;
        color: #374151;
    }
    #editorjs-container .gta6-youtube-tool input,
    #editorjs-container .gta6-youtube-tool textarea {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        padding: 0.65rem 0.75rem;
        background-color: #f9fafb;
        font-size: 0.95rem;
        transition: all 0.2s ease-in-out;
    }
    #editorjs-container .gta6-youtube-tool input:focus,
    #editorjs-container .gta6-youtube-tool textarea:focus {
        outline: none;
        border-color: #ec4899;
        box-shadow: 0 0 0 2px #fbcfe8;
        background-color: #fff;
    }
    #editorjs-container .gta6-youtube-tool .gta6-youtube-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 180px;
        border: 2px dashed #d1d5db;
        border-radius: 0.75rem;
        color: #9ca3af;
        background-color: #f9fafb;
        text-align: center;
        padding: 1rem;
        font-size: 0.95rem;
    }
    #editorjs-container .gta6-youtube-tool .gta6-youtube-placeholder.is-error {
        border-color: #fecdd3;
        color: #dc2626;
        background-color: #fef2f2;
    }
    .ce-toolbar {
        z-index: 40;
    }
    .ce-popover {
        max-width: min(320px, 90vw);
    }
    .ce-popover__content {
        max-height: 60vh;
        overflow-y: auto;
    }
    .ce-inline-toolbar {
        background-color: #1f2937;
        color: white;
        border-radius: 6px;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 1%), 0 2px 4px -2px rgb(0 0 0 / 4%);
    }
    .ce-inline-toolbar.ce-inline-toolbar--compact {
        left: 50% !important;
        transform: translateX(-50%);
        flex-wrap: wrap;
        gap: 0.35rem;
        padding: 0.5rem 0.75rem;
        max-width: calc(100vw - 2rem);
    }
    .ce-inline-toolbar .ce-inline-tool,
    .ce-inline-toolbar .ce-inline-tool svg {
        color: white;
    }
    .ce-inline-toolbar .ce-inline-tool:hover {
        background-color: #374151;
    }
    .ce-inline-toolbar .ce-inline-tool--active {
        background-color: #ec4899;
    }
    @media (max-width: 1024px) {
        #editorjs-container {
            padding: 0.85rem;
        }
    }
    @media (max-width: 768px) {
        #editorjs-container {
            min-height: 220px;
            padding: 0.75rem;
        }
        .ce-toolbar__plus,
        .ce-toolbar__settings-btn {
            width: 36px;
            height: 36px;
        }
        .ce-toolbar__actions {
            right: 0.35rem;
        }
    }
    @media (max-width: 640px) {
        #editorjs-container {
            padding: 0.65rem;
        }
        #editorjs-container .gta6-youtube-tool input,
        #editorjs-container .gta6-youtube-tool textarea {
            font-size: 0.9rem;
        }
        .ce-toolbar__plus {
            left: 0.35rem;
        }
        .ce-inline-toolbar {
            max-width: calc(100vw - 1rem);
        }
    }
</style>
<main class="container mx-auto p-4 lg:p-6">
    <div class="mb-4 lg:mb-6">
        <h2 class="text-xl lg:text-3xl font-bold text-gray-900"><?php esc_html_e('Update Mod:', 'gta6-mods'); ?> <a href="<?php echo esc_url(get_permalink($mod_id)); ?>" class="text-pink-600 hover:underline"><?php echo esc_html(get_the_title($mod_id)); ?></a></h2>
        <p class="text-gray-600 mt-1"><?php esc_html_e('You are currently editing this mod. Changes become visible after approval.', 'gta6-mods'); ?></p>
    </div>
    <div id="update-mod-root"></div>
</main>
<div id="uploading-overlay" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl p-6 sm:p-8 w-full max-w-md text-center space-y-4">
        <div id="uploading-spinner" class="flex items-center justify-center">
            <svg class="animate-spin h-12 w-12 text-pink-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z"></path>
            </svg>
        </div>
        <div>
            <p id="uploading-title" class="text-lg font-semibold text-gray-900"><?php esc_html_e('Submitting your update…', 'gta6-mods'); ?></p>
            <p id="uploading-status" class="text-sm text-gray-500"><?php esc_html_e('Please wait while we process your files.', 'gta6-mods'); ?></p>
        </div>
        <div id="progress-container" class="w-full pt-2 hidden">
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div id="upload-progress-bar" class="bg-pink-500 h-2.5 rounded-full transition-all duration-300 ease-linear" style="width: 0%"></div>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-2 text-sm text-gray-600">
                <span id="upload-progress-text" class="text-left font-semibold">0%</span>
                <span id="upload-speed-text" class="text-center"></span>
                <span id="upload-eta-text" class="text-right"></span>
            </div>
        </div>
    </div>
</div>
<?php
get_footer();
