<?php
/**
 * Template Name: Mod Upload
 */

if ( ! is_user_logged_in() ) {
    auth_redirect();
    exit;
}

global $current_user;
wp_get_current_user();

$available_categories    = [];
$misc_category_available = false;
$allowed_terms_map       = [];

if (function_exists('gta6mods_get_allowed_category_options')) {
    $available_categories = gta6mods_get_allowed_category_options();
}

if (function_exists('gta6mods_get_allowed_category_terms_map')) {
    $allowed_terms_map = gta6mods_get_allowed_category_terms_map();
    $misc_category_available = isset($allowed_terms_map['misc']);
}

$pending_mods = [];

$pending_query = new WP_Query([
    'author'         => get_current_user_id(),
    'post_type'      => 'post',
    'post_status'    => 'pending',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
]);

if ($pending_query->have_posts()) {
    foreach ($pending_query->posts as $pending_post_id) {
        $pending_post    = get_post($pending_post_id);
        if (!$pending_post instanceof WP_Post) {
            continue;
        }

        $submitted_timestamp = get_post_time('U', true, $pending_post);
        $submitted_label     = $submitted_timestamp
            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $submitted_timestamp)
            : '';
        $submitted_diff = $submitted_timestamp
            ? gta6_mods_format_time_ago($submitted_timestamp)
            : '';

        $preview_link = get_preview_post_link($pending_post);
        if (!$preview_link) {
            $preview_link = get_permalink($pending_post);
        }

        $pending_mods[] = [
            'id'              => $pending_post_id,
            'title'           => get_the_title($pending_post),
            'submitted_label' => $submitted_label,
            'submitted_diff'  => $submitted_diff,
            'preview'         => $preview_link,
        ];
    }
}

wp_reset_postdata();

$upload_data = [
    'ajax_url'    => admin_url( 'admin-ajax.php' ),
    'nonce'       => wp_create_nonce( 'gta6mods_mod_upload' ),
    'categories'  => $available_categories,
    'currentUser' => [
        'id'   => get_current_user_id(),
        'name' => $current_user->display_name ? $current_user->display_name : $current_user->user_login,
    ],
];

wp_enqueue_style(
    'gta6mods-photoswipe-upload',
    'https://unpkg.com/photoswipe@5/dist/photoswipe.css',
    [],
    '5.4.4'
);

?>
<?php get_header(); ?>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: #374151;
        }
        .logo-font {
            font-family: 'Oswald', sans-serif;
            font-weight: 600;
            color: #ffffff;
            font-size: 2rem;
            line-height: 1;
        }
        .btn-action {
            background-color: #ec4899;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px 0 rgba(236, 72, 153, 0.3);
        }
        .btn-action:hover {
            background-color: #db2777;
        }
        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            transition: background-color 0.3s ease;
        }
        .btn-secondary:hover {
            background-color: #d1d5db;
        }
        .card {
            background-color: white;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb78;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 1%), 0 2px 4px -2px rgb(0 0 0 / 4%);
            overflow: hidden;
        }

        #form-card {
            overflow: visible;
        }
        body.page-template-page-upload-mod main.container {
            max-width: 1200px;
        }
        .form-input, .form-select, .form-textarea, .form-url {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease-in-out;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus, .form-url:focus {
            outline: none;
            border-color: #ec4899;
            box-shadow: 0 0 0 2px #fbcfe8;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        .info-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        .dragging {
            opacity: 0.5;
        }
        .drag-over {
            border: 2px dashed #ec4899 !important;
        }
        .category-active {
            opacity: 1 !important;
            transform: scale(1.05);
            text-shadow: 0 0 8px rgba(255, 255, 255, 0.7);
        }
        .category-dimmed {
            opacity: 0.45 !important;
        }
        .preview-label {
            font-weight: 600;
            color: #4b5563;
        }
        .preview-value {
            color: #1f2937;
        }
        .sticky-sidebar {
            position: static;
        }
        /* Editor.js Custom Styles */
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
        /* Ensure text is readable */
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
        .ce-block__content, .ce-toolbar__content {
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

<script>
    const GTA6ModsUpload = <?php echo wp_json_encode( $upload_data ); ?>;
</script>
    <main class="container mx-auto p-4 lg:p-6">
        <h2 id="main-title" class="text-3xl font-bold text-gray-900 mb-6"><?php esc_html_e( 'Upload a new file', 'gta6-mods' ); ?></h2>

        <?php if ( ! empty( $pending_mods ) ) : ?>
            <section class="card p-6 md:p-8 mb-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-2"><?php esc_html_e( 'Pending submissions awaiting review', 'gta6-mods' ); ?></h3>
                <p class="text-gray-600 text-sm mb-4"><?php esc_html_e( 'These mods are waiting for a moderator to approve them. You can preview each submission while it is pending.', 'gta6-mods' ); ?></p>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ( $pending_mods as $pending_mod ) : ?>
                        <li class="py-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo esc_html( $pending_mod['title'] ); ?></p>
                                <?php if ( ! empty( $pending_mod['submitted_label'] ) ) : ?>
                                    <p class="text-sm text-gray-500">
                                        <?php
                                        if ( ! empty( $pending_mod['submitted_diff'] ) ) {
                                            printf(
                                                /* translators: 1: Submission date, 2: Human readable difference */
                                                esc_html__( 'Submitted on %1$s • %2$s ago', 'gta6-mods' ),
                                                esc_html( $pending_mod['submitted_label'] ),
                                                esc_html( $pending_mod['submitted_diff'] )
                                            );
                                        } else {
                                            printf(
                                                /* translators: %s: Submission date */
                                                esc_html__( 'Submitted on %s', 'gta6-mods' ),
                                                esc_html( $pending_mod['submitted_label'] )
                                            );
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php if ( ! empty( $pending_mod['preview'] ) ) : ?>
                                <a href="<?php echo esc_url( $pending_mod['preview'] ); ?>" class="inline-flex items-center self-start text-sm font-semibold text-pink-600 hover:text-pink-800 transition" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-eye mr-2"></i>
                                    <?php esc_html_e( 'Preview submission', 'gta6-mods' ); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <div id="preview-mode-banner" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-6" role="alert">
            <p class="font-bold"><?php esc_html_e( 'Preview Mode', 'gta6-mods' ); ?></p>
            <p><?php esc_html_e( 'This is how your mod page will look. Review the details below before submitting.', 'gta6-mods' ); ?></p>
        </div>

        <div id="form-card" class="card p-6 md:p-8">
            <form id="upload-form" action="#" method="POST" enctype="multipart/form-data">
                <div id="form-step-1">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div>
                                <label for="file-name" class="form-label"><?php esc_html_e( 'File Name', 'gta6-mods' ); ?></label>
                                <input type="text" id="file-name" name="file-name" class="form-input" required>
                            </div>

                            <div>
                                <label class="form-label"><?php esc_html_e( 'Author(s)', 'gta6-mods' ); ?></label>
                                <div id="authors-container" class="space-y-2"></div>
                                <button type="button" id="add-author-btn" class="mt-2 text-sm font-semibold text-pink-600 hover:text-pink-800 transition">+ <?php esc_html_e( 'Add Author', 'gta6-mods' ); ?></button>
                            </div>

                            <div>
                                <label for="category" class="form-label"><?php esc_html_e( 'Category', 'gta6-mods' ); ?></label>
                                <select id="category" name="category" class="form-select cursor-pointer">
                                    <option value=""><?php esc_html_e( 'Select a category...', 'gta6-mods' ); ?></option>
                                </select>
                            </div>

                            <div>
                                <label for="tags" class="form-label"><?php esc_html_e( 'Tags', 'gta6-mods' ); ?></label>
                                <input type="text" id="tags" name="tags" class="form-input" placeholder="e.g. car, addon, tuning, classic">
                            </div>

                            <div>
                                <label id="description-label" class="form-label"><?php esc_html_e( 'Description', 'gta6-mods' ); ?></label>
                                <div id="editorjs-container" role="textbox" aria-multiline="true" aria-labelledby="description-label"></div>
                                <input type="hidden" id="description" name="description">
                            </div>
                        </div>

                        <div class="lg:col-span-1 space-y-6 lg:self-start sticky-sidebar">
                            <div>
                                <label class="form-label"><?php esc_html_e( 'Add screenshots', 'gta6-mods' ); ?></label>
                                <div class="flex items-center justify-center w-full">
                                    <label id="dropzone-label" for="screenshot-upload" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                                            <p class="mb-2 text-sm text-gray-500"><span class="font-semibold"><?php esc_html_e( 'Click to upload', 'gta6-mods' ); ?></span> <?php esc_html_e( 'or drag and drop', 'gta6-mods' ); ?></p>
                                            <p class="text-xs text-gray-500"><?php esc_html_e( 'JPG, PNG, WEBP (MAX. 10MB)', 'gta6-mods' ); ?></p>
                                        </div>
                                        <input id="screenshot-upload" type="file" class="hidden" multiple accept="image/png, image/jpeg, image/webp" />
                                    </label>
                                </div>
                                <p class="text-xs text-gray-500 mt-1"><?php esc_html_e( 'The first image is the default featured image. You can drag to reorder.', 'gta6-mods' ); ?></p>
                                <div id="image-preview-container" class="mt-4 flex flex-wrap gap-4"></div>
                            </div>

                            <div>
                                <h3 class="form-label"><?php esc_html_e( 'File Settings', 'gta6-mods' ); ?></h3>
                                <div class="info-box space-y-3">
                                    <h4 class="font-semibold text-gray-800"><?php esc_html_e( 'Video Upload Permissions', 'gta6-mods' ); ?></h4>
                                    <div class="flex items-center">
                                        <input id="video-deny" name="video-permissions" type="radio" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" value="deny">
                                        <label for="video-deny" class="ml-3 block text-sm font-medium text-gray-700"><?php esc_html_e( 'Deny', 'gta6-mods' ); ?></label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="video-moderate" name="video-permissions" type="radio" checked class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" value="moderate">
                                        <label for="video-moderate" class="ml-3 block text-sm font-medium text-gray-700"><?php esc_html_e( 'Self Moderate', 'gta6-mods' ); ?></label>
                                    </div>
                                    <div class="flex items-center">
                                        <input id="video-allow" name="video-permissions" type="radio" class="focus:ring-pink-500 h-4 w-4 text-pink-600 border-gray-300" value="allow">
                                        <label for="video-allow" class="ml-3 block text-sm font-medium text-gray-700"><?php esc_html_e( 'Allow', 'gta6-mods' ); ?></label>
                                    </div>
                                </div>
                            </div>

                            <div class="info-box text-sm">
                                <p class="font-semibold mb-2 text-gray-800"><?php esc_html_e( 'Please ensure you upload an in-game image or representative art of your mod.', 'gta6-mods' ); ?></p>
                                <p class="mb-2"><?php esc_html_e( 'The description must include:', 'gta6-mods' ); ?></p>
                                <ul class="list-disc list-inside space-y-1 text-xs text-gray-600">
                                    <li><?php esc_html_e( 'Mod description', 'gta6-mods' ); ?></li>
                                    <li><?php esc_html_e( 'Bugs and features', 'gta6-mods' ); ?></li>
                                    <li><?php esc_html_e( 'Summary of installation instructions', 'gta6-mods' ); ?></li>
                                    <li><?php esc_html_e( 'Credits and, if applicable, notices of permission for content re-use', 'gta6-mods' ); ?></li>
                                </ul>
                                <p class="mt-2"><?php esc_html_e( 'Failing to provide the necessary details will result in your mod being rejected.', 'gta6-mods' ); ?></p>
                            </div>

                            <div id="step-1-errors" class="mb-4"></div>

                            <div class="mt-6 flex items-center justify-end space-x-3">
                                <button type="button" class="btn-secondary font-bold py-2 px-6 rounded-lg transition" onclick="window.history.back();"><?php esc_html_e( 'Cancel', 'gta6-mods' ); ?></button>
                                <button type="button" id="continue-btn" class="btn-action font-bold py-2 px-6 rounded-lg transition"><?php esc_html_e( 'Continue', 'gta6-mods' ); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                    <div id="form-step-2" class="hidden">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4"><?php esc_html_e( 'Upload Your File', 'gta6-mods' ); ?></h3>
                        <div id="step-2-errors" class="mb-4"></div>
                        <div class="space-y-6">
                            <div>
                                <div id="file-upload-view">
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="form-label !mb-0"><?php esc_html_e( 'Mod File', 'gta6-mods' ); ?></label>
                                        <button type="button" id="show-url-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition"><?php esc_html_e( 'Or provide a download link', 'gta6-mods' ); ?></button>
                                    </div>
                                    <div class="flex items-center justify-center w-full">
                                        <label id="mod-dropzone-label" for="mod-file-input" class="flex flex-col items-center justify-center w-full h-48 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                            <div id="mod-dropzone-content" class="flex flex-col items-center justify-center pt-5 pb-6 text-center">
                                                <i class="fas fa-file-archive text-5xl text-gray-400"></i>
                                                <p class="my-2 text-gray-500"><span class="font-semibold"><?php esc_html_e( 'Click to upload', 'gta6-mods' ); ?></span> <?php esc_html_e( 'or drag and drop', 'gta6-mods' ); ?></p>
                                                <p class="text-xs text-gray-500"><?php esc_html_e( 'Allowed: .zip, .rar, .7z, .oiv (MAX. 400MB)', 'gta6-mods' ); ?></p>
                                            </div>
                                            <div id="mod-file-preview" class="hidden items-center justify-center text-center p-4"></div>
                                            <input id="mod-file-input" type="file" class="hidden" accept=".zip,.rar,.7z,.oiv" />
                                        </label>
                                    </div>
                                </div>

                                <div id="url-view" class="hidden">
                                    <div class="flex justify-between items-center mb-2">
                                        <label class="form-label !mb-0" for="mod-url-input"><?php esc_html_e( 'Download URL', 'gta6-mods' ); ?></label>
                                        <button type="button" id="show-file-view-btn" class="text-sm font-semibold text-pink-600 hover:text-pink-800 transition"><?php esc_html_e( 'Or upload a file', 'gta6-mods' ); ?></button>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <input type="url" id="mod-url-input" name="mod-url" class="form-url" placeholder="<?php esc_attr_e( 'e.g. https://drive.google.com/file/d/...', 'gta6-mods' ); ?>">
                                            <p class="text-xs text-gray-500 mt-2"><?php esc_html_e( 'Provide a direct download link from a trusted source (e.g., Google Drive, Mega, Dropbox).', 'gta6-mods' ); ?></p>
                                        </div>
                                        <div>
                                            <label for="file-size-input" class="form-label text-sm"><?php esc_html_e( 'File Size', 'gta6-mods' ); ?></label>
                                            <div class="flex items-center gap-2">
                                                <input type="number" min="0" step="0.01" id="file-size-input" name="file-size" class="form-input" placeholder="<?php esc_attr_e( 'e.g. 850', 'gta6-mods' ); ?>">
                                                <select id="file-size-unit" name="file-size-unit" class="form-select !w-auto">
                                                    <option value="MB"><?php esc_html_e( 'MB', 'gta6-mods' ); ?></option>
                                                    <option value="GB"><?php esc_html_e( 'GB', 'gta6-mods' ); ?></option>
                                                </select>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2"><?php esc_html_e( 'Please specify the file size to inform users about the download.', 'gta6-mods' ); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="version" class="form-label"><?php esc_html_e( 'Version', 'gta6-mods' ); ?></label>
                                <input type="text" id="version" name="version" class="form-input" placeholder="e.g. 1.0.0">
                            </div>

                            <div class="border-t pt-6 flex items-center justify-between">
                                <button type="button" id="back-btn" class="btn-secondary font-bold py-2 px-6 rounded-lg transition"><?php esc_html_e( 'Back', 'gta6-mods' ); ?></button>
                                <button type="button" id="preview-btn" class="btn-action font-bold py-2 px-6 rounded-lg transition"><?php esc_html_e( 'Preview', 'gta6-mods' ); ?></button>
                            </div>
                            <p class="text-xs text-gray-500"><?php esc_html_e( 'Please follow the upload rules or your file will be removed.', 'gta6-mods' ); ?></p>
                        </div>
                    </div>

                    <div id="form-step-3" class="hidden">
                        <div id="preview-header" class="mb-6"></div>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            <div class="lg:col-span-2 space-y-6">
                                <div>
                                    <div id="preview-gallery-container" class="pswp-gallery">
                                        <div class="aspect-video bg-gray-200 rounded-lg overflow-hidden mb-4 shadow-inner"></div>
                                        <div id="preview-gallery-thumbnails" class="grid grid-cols-5 gap-2"></div>
                                    </div>
                                    <div id="preview-load-more-container" class="mt-4"></div>
                                </div>

                                <div class="info-box">
                                    <h3 class="text-lg font-bold text-gray-900 mb-3 border-b pb-3"><?php esc_html_e( 'Description', 'gta6-mods' ); ?></h3>
                                    <div id="preview-description-content" class="prose max-w-none text-gray-700"></div>
                                </div>
                            </div>

                            <div class="lg:col-span-1 space-y-6 self-start sticky-sidebar">
                                <div class="info-box p-4 space-y-4">
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                        <span class="preview-label"><?php esc_html_e( 'Author:', 'gta6-mods' ); ?></span>
                                        <span id="preview-sidebar-authors" class="preview-value font-semibold text-right truncate"></span>

                                        <span class="preview-label"><?php esc_html_e( 'Version:', 'gta6-mods' ); ?></span>
                                        <span id="preview-sidebar-version" class="preview-value font-semibold text-right"></span>

                                        <span class="preview-label"><?php esc_html_e( 'Last Updated:', 'gta6-mods' ); ?></span>
                                        <span id="preview-sidebar-updated" class="preview-value text-right"></span>

                                        <span class="preview-label"><?php esc_html_e( 'Category:', 'gta6-mods' ); ?></span>
                                        <a href="#" id="preview-sidebar-category" class="preview-value text-pink-600 hover:underline text-right"></a>
                                    </div>
                                    <div class="border-t pt-4">
                                        <span class="preview-label"><?php esc_html_e( 'Tags:', 'gta6-mods' ); ?></span>
                                        <div id="preview-sidebar-tags" class="flex flex-wrap gap-2 mt-2"></div>
                                    </div>
                                </div>
                                <div class="info-box p-4">
                                    <div class="grid grid-cols-3 gap-2 text-sm text-center">
                                        <div>
                                            <span class="font-bold text-lg text-gray-800">0</span>
                                            <span class="text-xs text-gray-500 block"><?php esc_html_e( 'Likes', 'gta6-mods' ); ?></span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-lg text-gray-800">0</span>
                                            <span class="text-xs text-gray-500 block"><?php esc_html_e( 'Views', 'gta6-mods' ); ?></span>
                                        </div>
                                        <div>
                                            <span class="font-bold text-lg text-gray-800">0</span>
                                            <span class="text-xs text-gray-500 block"><?php esc_html_e( 'Downloads', 'gta6-mods' ); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="info-box p-4">
                                    <p class="preview-label"><?php esc_html_e( 'Mod Source:', 'gta6-mods' ); ?></p>
                                    <div id="preview-sidebar-source-info" class="mt-1"></div>
                                </div>

                                <div class="space-y-3">
                                    <button type="submit" class="w-full btn-action font-bold py-3 px-6 rounded-lg transition text-lg flex items-center justify-center gap-2">
                                        <i class="fas fa-check-circle"></i> <?php esc_html_e( 'Submit File', 'gta6-mods' ); ?>
                                    </button>
                                    <button type="button" id="back-to-step-2-btn" class="w-full btn-secondary font-bold py-2 px-6 rounded-lg transition"><?php esc_html_e( 'Back to Edit', 'gta6-mods' ); ?></button>
                                </div>

                                <div id="submission-errors" class="text-sm text-red-600"></div>
                            </div>
                        </div>
                    </div>
                </form>
        </div>

        <div id="upload-rules" class="card mt-8">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900"><?php esc_html_e( 'Upload Rules', 'gta6-mods' ); ?></h3>
            </div>
            <div class="p-6 text-sm text-gray-600">
                <p class="font-semibold mb-3"><?php esc_html_e( 'DO NOT upload any of the following items - breaking these rules will cause your file to be deleted without notice:', 'gta6-mods' ); ?></p>
                <ul class="list-disc list-inside space-y-2">
                    <li><?php esc_html_e( 'Any files besides .zip, .rar, .7z and .oiv archives.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Archives that do not contain a mod, or are part of other mods or mod packs.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Any archive containing only original game files.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Any file that can be used for cheating online.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Any file containing or giving access to pirated or otherwise copyrighted content including game cracks, movies, television shows and music.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Files containing malware or any .EXE file with a positive anti-virus result.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Any file containing nude or semi-nude pornographic images.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Any file containing a political or ideology theme. At the complete discretion of the administrator, deemed to be something that will cause unnecessary debates in the comments section.', 'gta6-mods' ); ?></li>
                    <li><?php esc_html_e( 'Files that do not contain a simple installation instruction, with an exception for tools.', 'gta6-mods' ); ?></li>
                </ul>
                <p class="mt-4"><?php esc_html_e( 'For a full list of our rules and regulations please see: Rules and Regulations.', 'gta6-mods' ); ?></p>
            </div>
        </div>
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
                <p id="uploading-title" class="text-lg font-semibold text-gray-900"><?php esc_html_e( 'Uploading your mod…', 'gta6-mods' ); ?></p>
                <p id="uploading-status" class="text-sm text-gray-500"><?php esc_html_e( 'Please wait while we process your files.', 'gta6-mods' ); ?></p>
            </div>
            <!-- Progress bar HTML structure -->
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/theia-sticky-sidebar@1.7.0/dist/theia-sticky-sidebar.min.js"></script>
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5/dist/photoswipe-lightbox.esm.js';

        document.addEventListener('DOMContentLoaded', function() {
            let pswpLightbox = null;
            let editor;
            let inlineToolbarObserver = null;
            let backspaceHandlerAttached = false;

            const desktopBreakpoint = 1024;
            const stickyMarginTop = 24;
            const stickyMarginBottom = 24;
            const $ = window.jQuery;
            const descriptionInput = document.getElementById('description');
            const uploadForm = document.getElementById('upload-form');
            const submissionErrors = document.getElementById('submission-errors');
            const editorContainer = document.getElementById('editorjs-container');

            const youtubeStrings = {
                urlLabel: '<?php echo esc_js( __( 'YouTube URL', 'gta6-mods' ) ); ?>',
                urlPlaceholder: '<?php echo esc_js( __( 'Paste a YouTube link (e.g. https://youtu.be/...)', 'gta6-mods' ) ); ?>',
                helper: '<?php echo esc_js( __( 'Supports standard, share and Shorts links.', 'gta6-mods' ) ); ?>',
                captionLabel: '<?php echo esc_js( __( 'Caption', 'gta6-mods' ) ); ?>',
                captionPlaceholder: '<?php echo esc_js( __( 'Optional caption', 'gta6-mods' ) ); ?>',
                placeholder: '<?php echo esc_js( __( 'Paste a YouTube link to preview the video.', 'gta6-mods' ) ); ?>',
                invalidMessage: '<?php echo esc_js( __( 'Please enter a valid YouTube URL.', 'gta6-mods' ) ); ?>',
            };

            const debounce = (fn, delay = 300) => {
                let timeoutId;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => {
                        fn(...args);
                    }, delay);
                };
            };

            const extractYoutubeId = (url) => {
                if (!url) {
                    return '';
                }

                try {
                    const parsed = new URL(url.trim());
                    const hostname = parsed.hostname.toLowerCase();
                    const normalizedHost = hostname.replace(/^www\./, '');

                    if (normalizedHost === 'youtu.be') {
                        const pathParts = parsed.pathname.split('/').filter(Boolean);
                        return pathParts[0] || '';
                    }

                    if (normalizedHost === 'youtube.com' || normalizedHost === 'm.youtube.com' || normalizedHost.endsWith('.youtube.com') || normalizedHost === 'youtube-nocookie.com') {
                        if (parsed.pathname.startsWith('/embed/')) {
                            return parsed.pathname.split('/')[2] || '';
                        }
                        if (parsed.pathname.startsWith('/shorts/')) {
                            return parsed.pathname.split('/')[2] || '';
                        }
                        if (parsed.pathname.startsWith('/live/')) {
                            return parsed.pathname.split('/')[2] || '';
                        }
                        return parsed.searchParams.get('v') || '';
                    }
                } catch (error) {
                    return '';
                }

                return '';
            };

            const getYoutubeEmbedUrl = (videoId) => videoId ? `https://www.youtube.com/embed/${videoId}?rel=0` : '';
            const getYoutubeCanonicalUrl = (videoId) => videoId ? `https://www.youtube.com/watch?v=${videoId}` : '';

            const applyInlineToolbarMode = (toolbarEl) => {
                const toolbar = toolbarEl || document.querySelector('.ce-inline-toolbar');
                if (!toolbar) {
                    return;
                }

                if (window.innerWidth < 768) {
                    toolbar.classList.add('ce-inline-toolbar--compact');
                } else {
                    toolbar.classList.remove('ce-inline-toolbar--compact');
                }
            };

            const observeInlineToolbar = () => {
                if (inlineToolbarObserver) {
                    inlineToolbarObserver.disconnect();
                }

                inlineToolbarObserver = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            if (node instanceof HTMLElement && node.classList.contains('ce-inline-toolbar')) {
                                applyInlineToolbarMode(node);
                            }
                        });
                    });
                });

                inlineToolbarObserver.observe(document.body, { childList: true });
            };

            const ensureEditorContainerFocusability = () => {
                if (!editorContainer) {
                    return;
                }

                editorContainer.addEventListener('mousedown', (event) => {
                    if (!editor || typeof editor.focus !== 'function') {
                        return;
                    }

                    const target = event.target;
                    if (!(target instanceof Element)) {
                        return;
                    }

                    if (target.closest('input, textarea, button, select, label, a, .gta6-youtube-tool')) {
                        return;
                    }

                    const redactor = editorContainer.querySelector('.codex-editor__redactor');

                    if (target !== editorContainer) {
                        if (!redactor || !redactor.contains(target)) {
                            return;
                        }

                        if (target.closest('[contenteditable="true"]')) {
                            return;
                        }
                    }

                    window.requestAnimationFrame(() => {
                        const getBlockCount = () => {
                            if (!editor || !editor.blocks || typeof editor.blocks.getBlocksCount !== 'function') {
                                return 0;
                            }

                            try {
                                return editor.blocks.getBlocksCount();
                            } catch (error) {
                                return 0;
                            }
                        };

                        const blockCount = getBlockCount();

                        if (blockCount === 0 && editor?.blocks && typeof editor.blocks.insert === 'function') {
                            try {
                                editor.blocks.insert('paragraph', { text: '' }, undefined, undefined, true);
                            } catch (insertError) {
                                console.warn('Failed to insert fallback paragraph block', insertError);
                            }
                        }

                        if (editor?.caret && typeof editor.caret.setToLastBlock === 'function') {
                            try {
                                editor.caret.setToLastBlock('end');
                                return;
                            } catch (caretError) {
                                console.warn('Failed to move caret to the last block', caretError);
                            }
                        }

                        const finalBlockCount = getBlockCount();
                        if (finalBlockCount > 0 && editor?.caret && typeof editor.caret.setToBlock === 'function') {
                            try {
                                editor.caret.setToBlock(finalBlockCount - 1, 'end');
                                return;
                            } catch (fallbackError) {
                                console.warn('Failed to place caret using setToBlock', fallbackError);
                            }
                        }

                        if (editor && typeof editor.focus === 'function') {
                            try {
                                editor.focus({ preventScroll: true });
                            } catch (focusError) {
                                try {
                                    editor.focus();
                                } catch (finalFocusError) {
                                    console.warn('Editor focus failed', finalFocusError);
                                }
                            }
                        }
                    });
                });
            };

            const isElementEffectivelyEmpty = (element) => {
                if (!(element instanceof HTMLElement)) {
                    return true;
                }

                const clone = element.cloneNode(true);

                clone.querySelectorAll('br, wbr').forEach((node) => node.remove());
                clone.querySelectorAll('[data-placeholder], .ce-block__placeholder').forEach((node) => node.remove());

                const hasContentElement = clone.querySelector('img, video, audio, iframe, table, pre, code, input, textarea, embed, object');
                if (hasContentElement) {
                    return false;
                }

                const showText = typeof NodeFilter !== 'undefined' ? NodeFilter.SHOW_TEXT : 4;
                const filterAccept = typeof NodeFilter !== 'undefined' ? NodeFilter.FILTER_ACCEPT : 1;
                const filterSkip = typeof NodeFilter !== 'undefined' ? NodeFilter.FILTER_SKIP : 3;

                const walker = document.createTreeWalker(clone, showText, {
                    acceptNode(node) {
                        if (!node || typeof node.nodeValue !== 'string') {
                            return filterSkip;
                        }

                        const value = node.nodeValue.replace(/\u200B/g, '').trim();
                        return value.length > 0 ? filterAccept : filterSkip;
                    }
                });

                if (walker.nextNode()) {
                    return false;
                }

                const sanitized = clone.innerHTML
                    .replace(/<[^>]+>/g, '')
                    .replace(/&nbsp;/gi, '')
                    .replace(/\u200B/g, '')
                    .trim();

                return sanitized.length === 0;
            };

            const focusContentEditable = (element, position = 'end') => {
                if (!(element instanceof HTMLElement) || !element.isContentEditable) {
                    return;
                }

                try {
                    element.focus({ preventScroll: true });
                } catch (focusError) {
                    element.focus();
                }

                const selection = window.getSelection();
                if (!selection) {
                    return;
                }

                const range = document.createRange();
                range.selectNodeContents(element);
                range.collapse(position === 'start');

                selection.removeAllRanges();
                selection.addRange(range);
            };

            const moveCaretAfterBlockDeletion = (blockIndex) => {
                window.requestAnimationFrame(() => {
                    const targetIndex = Math.max(0, blockIndex - 1);
                    let caretPlaced = false;

                    if (typeof editor?.caret?.setToBlock === 'function') {
                        try {
                            editor.caret.setToBlock(targetIndex, 'end');
                            caretPlaced = true;
                        } catch (caretError) {
                            caretPlaced = false;
                        }
                    }

                    if (!caretPlaced && typeof editor?.blocks?.insert === 'function') {
                        try {
                            const insertIndex = Math.max(0, blockIndex - 1);
                            editor.blocks.insert('paragraph', { text: '' }, undefined, insertIndex, true);

                            window.requestAnimationFrame(() => {
                                if (typeof editor?.caret?.setToBlock === 'function') {
                                    try {
                                        editor.caret.setToBlock(insertIndex, 'end');
                                    } catch (finalCaretError) {
                                        console.warn('Failed to focus fallback paragraph', finalCaretError);
                                    }
                                }
                            });
                        } catch (insertError) {
                            console.warn('Failed to insert fallback paragraph after deleting list block', insertError);
                        }
                    }
                });
            };

            const handleRedactorBackspace = (event) => {
                if (event.key !== 'Backspace' || event.defaultPrevented) {
                    return;
                }

                if (event.__gta6ListHandled) {
                    return;
                }

                if (!editor || !editor.blocks || typeof editor.blocks.getCurrentBlockIndex !== 'function') {
                    return;
                }

                const blockIndex = editor.blocks.getCurrentBlockIndex();
                if (blockIndex <= 0) {
                    return;
                }

                const block = editor.blocks.getBlockByIndex(blockIndex);
                if (!block) {
                    return;
                }

                const blockName = typeof block.name === 'string' ? block.name.toLowerCase() : '';
                if (['list', 'checklist'].includes(blockName)) {
                    return;
                }

                const holder = block.holder;
                if (!holder) {
                    return;
                }

                const holderDataset = holder.dataset || {};
                const datasetType = typeof holderDataset.type === 'string' ? holderDataset.type.toLowerCase() : '';
                const datasetTool = typeof holderDataset.tool === 'string' ? holderDataset.tool.toLowerCase() : '';
                if (['list', 'checklist'].includes(datasetType) || ['list', 'checklist'].includes(datasetTool)) {
                    return;
                }

                if (holder.closest('.gta6-youtube-tool')) {
                    return;
                }

                const hasInteractiveChild = holder.querySelector('iframe, img, video, audio, table, pre, code, .gta6-youtube-tool');
                if (hasInteractiveChild) {
                    return;
                }

                const selection = window.getSelection();
                if (selection && !selection.isCollapsed) {
                    return;
                }

                const textContent = holder.innerText.replace(/\u200B/g, '').trim();
                if (textContent.length > 0) {
                    return;
                }

                event.preventDefault();

                try {
                    editor.blocks.delete(blockIndex);
                } catch (deleteError) {
                    console.warn('Failed to delete empty block', deleteError);
                    return;
                }

                moveCaretAfterBlockDeletion(blockIndex);
            };

            const attachBackspaceHandler = () => {
                if (backspaceHandlerAttached || !editorContainer) {
                    return;
                }

                const redactor = editorContainer.querySelector('.codex-editor__redactor');
                if (!redactor) {
                    return;
                }

                redactor.addEventListener('keydown', handleRedactorBackspace);
                backspaceHandlerAttached = true;
            };

            const patchListBackspaceBehavior = () => {
                const ListTool = window.List;
                if (!ListTool || ListTool.__gta6BackspacePatched) {
                    return;
                }

                const originalBackspace = (ListTool.prototype && typeof ListTool.prototype.backspace === 'function')
                    ? ListTool.prototype.backspace
                    : null;

                const getItemElements = (instance) => {
                    if (!instance || !instance._elements || !instance._elements.wrapper || !instance.CSS || !instance.CSS.item) {
                        return [];
                    }

                    return Array.from(instance._elements.wrapper.querySelectorAll(`.${instance.CSS.item}`));
                };

                ListTool.prototype.backspace = function(event) {
                    if (!event) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    if (event.defaultPrevented) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    const selection = window.getSelection();
                    if (!selection || !selection.isCollapsed) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    const anchorNode = selection.anchorNode;
                    if (!anchorNode) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    const itemClass = this?.CSS?.item;
                    const wrapper = this?._elements?.wrapper;
                    if (!itemClass || !wrapper) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    let baseElement = anchorNode instanceof Element ? anchorNode : anchorNode.parentElement;
                    if (!(baseElement instanceof Element)) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    const itemSelector = `.${itemClass}`;
                    const currentItem = baseElement.closest(itemSelector);
                    if (!currentItem) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    if (!isElementEffectivelyEmpty(currentItem)) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    event.preventDefault();
                    event.__gta6ListHandled = true;

                    const itemsBeforeRemoval = getItemElements(this);
                    const currentIndex = itemsBeforeRemoval.indexOf(currentItem);

                    if (currentIndex === -1) {
                        return originalBackspace ? originalBackspace.call(this, event) : undefined;
                    }

                    currentItem.remove();

                    const remainingItems = getItemElements(this);
                    if (Array.isArray(this?._data?.items)) {
                        this._data.items = remainingItems.map((itemEl) => itemEl.innerHTML);
                    }

                    if (remainingItems.length === 0) {
                        const blockIndex = this?.api?.blocks?.getCurrentBlockIndex?.();
                        if (typeof blockIndex === 'number' && blockIndex >= 0 && this?.api?.blocks?.delete) {
                            try {
                                this.api.blocks.delete(blockIndex);
                            } catch (deleteError) {
                                console.warn('Failed to delete list block after removing final item', deleteError);
                            }

                            moveCaretAfterBlockDeletion(blockIndex);
                        }
                    } else {
                        const previousItem = currentIndex > 0 ? remainingItems[currentIndex - 1] : null;
                        const fallbackIndex = Math.min(currentIndex, remainingItems.length - 1);
                        const fallbackItem = remainingItems[fallbackIndex];
                        const focusItem = previousItem || fallbackItem;
                        const focusPosition = previousItem ? 'end' : 'start';

                        if (focusItem) {
                            const editableTarget = focusItem.matches('[contenteditable="true"]')
                                ? focusItem
                                : focusItem.querySelector('[contenteditable="true"]');

                            if (editableTarget instanceof HTMLElement) {
                                focusContentEditable(editableTarget, focusPosition);
                            }
                        }

                        remainingItems.forEach((itemEl, index) => {
                            if (itemEl.dataset) {
                                itemEl.dataset.item = String(index + 1);
                            }
                        });
                    }

                    if (typeof this?.api?.dispatchChange === 'function') {
                        try {
                            this.api.dispatchChange();
                        } catch (dispatchError) {
                            console.warn('Failed to dispatch change after list item removal', dispatchError);
                        }
                    } else if (typeof this?.api?.events?.emit === 'function') {
                        try {
                            this.api.events.emit('block-changed');
                        } catch (emitError) {
                            console.warn('Failed to emit block change after list item removal', emitError);
                        }
                    }

                    return undefined;
                };

                ListTool.__gta6BackspacePatched = true;
            };

            class GTA6YoutubeTool {
                constructor({ data = {}, api, readOnly }) {
                    this.api = api;
                    this.readOnly = readOnly;
                    this.data = data || {};
                    this.wrapper = null;
                    this.urlInput = null;
                    this.captionInput = null;
                    this.previewContainer = null;
                    const initialVideoId = this.data.videoId || extractYoutubeId(this.data.url || this.data.originalUrl || '');
                    if (initialVideoId) {
                        this.data.videoId = initialVideoId;
                        if (!this.data.url) {
                            this.data.url = getYoutubeCanonicalUrl(initialVideoId);
                        }
                        if (!this.data.embedUrl) {
                            this.data.embedUrl = getYoutubeEmbedUrl(initialVideoId);
                        }
                    }
                    if (!this.data.originalUrl && this.data.url) {
                        this.data.originalUrl = this.data.url;
                    }
                    const hasExistingUrl = typeof this.data.url === 'string' && this.data.url.trim().length > 0;
                    this.shouldAutofocusUrl = !hasExistingUrl;
                    this.hasAutofocusedUrl = false;
                }

                static get toolbox() {
                    return {
                        title: 'YouTube',
                        icon: '<svg width="17" height="15" viewBox="0 0 17 15" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M16.58 3.12a2.06 2.06 0 00-1.46-1.46C13.84 1.25 8.5 1.25 8.5 1.25s-5.34 0-6.62.41A2.06 2.06 0 00.42 3.12 21.48 21.48 0 000 7.5a21.48 21.48 0 00.42 4.38 2.06 2.06 0 001.46 1.46c1.28.41 6.62.41 6.62.41s5.34 0 6.62-.41a2.06 2.06 0 001.46-1.46A21.48 21.48 0 0017 7.5a21.48 21.48 0 00-.42-4.38zM6.8 10.3V4.7l4.43 2.8z"/></svg>'
                    };
                }

                static get sanitize() {
                    return {
                        url: false,
                        videoId: false,
                        embedUrl: false,
                        originalUrl: false,
                        service: false,
                        caption: true,
                    };
                }

                static get isReadOnlySupported() {
                    return true;
                }

                render() {
                    this.wrapper = document.createElement('div');
                    this.wrapper.classList.add('gta6-youtube-tool');

                    const videoId = this.data.videoId || extractYoutubeId(this.data.url || this.data.originalUrl || '');

                    if (this.readOnly) {
                        const embedSrc = this.data.embedUrl || getYoutubeEmbedUrl(videoId);
                        if (videoId && embedSrc) {
                            const preview = document.createElement('div');
                            preview.className = 'gta6-editor-embed';
                            preview.innerHTML = `<iframe src="${embedSrc}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`;
                            this.wrapper.appendChild(preview);
                        }

                        if (this.data.caption) {
                            const caption = document.createElement('p');
                            caption.className = 'text-center text-sm text-gray-500 italic mt-2';
                            caption.textContent = this.data.caption;
                            this.wrapper.appendChild(caption);
                        }

                        return this.wrapper;
                    }

                    const urlField = document.createElement('div');
                    const urlLabel = document.createElement('label');
                    urlLabel.textContent = youtubeStrings.urlLabel;
                    this.urlInput = document.createElement('input');
                    this.urlInput.type = 'url';
                    this.urlInput.placeholder = youtubeStrings.urlPlaceholder;
                    const initialUrl = this.data.originalUrl || this.data.url || '';
                    this.urlInput.value = initialUrl;
                    this.urlInput.autocomplete = 'off';
                    this.urlInput.spellcheck = false;
                    urlField.appendChild(urlLabel);
                    urlField.appendChild(this.urlInput);

                    const helperText = document.createElement('p');
                    helperText.className = 'text-xs text-gray-500';
                    helperText.textContent = youtubeStrings.helper;
                    urlField.appendChild(helperText);

                    this.wrapper.appendChild(urlField);

                    this.previewContainer = document.createElement('div');
                    this.wrapper.appendChild(this.previewContainer);

                    const captionField = document.createElement('div');
                    const captionLabel = document.createElement('label');
                    captionLabel.textContent = youtubeStrings.captionLabel;
                    this.captionInput = document.createElement('input');
                    this.captionInput.type = 'text';
                    this.captionInput.placeholder = youtubeStrings.captionPlaceholder;
                    this.captionInput.value = this.data.caption || '';
                    this.captionInput.autocomplete = 'off';
                    captionField.appendChild(captionLabel);
                    captionField.appendChild(this.captionInput);

                    this.wrapper.appendChild(captionField);

                    const stopPropagation = (element) => {
                        if (!element) {
                            return;
                        }

                        ['click', 'mousedown', 'touchstart'].forEach((eventName) => {
                            element.addEventListener(eventName, (event) => event.stopPropagation());
                        });

                        element.addEventListener('keydown', (event) => event.stopPropagation());
                    };

                    stopPropagation(this.urlInput);
                    stopPropagation(this.captionInput);

                    const markManualInteraction = () => {
                        this.shouldAutofocusUrl = false;
                    };

                    if (this.wrapper) {
                        ['pointerdown', 'focusin'].forEach((eventName) => {
                            this.wrapper.addEventListener(eventName, markManualInteraction);
                        });
                    }

                    this.urlInput.addEventListener('focus', markManualInteraction);
                    this.urlInput.addEventListener('input', () => {
                        const value = this.urlInput.value.trim();
                        this.data.originalUrl = value;
                        this.data.videoId = extractYoutubeId(value);
                        this.data.url = this.data.videoId ? getYoutubeCanonicalUrl(this.data.videoId) : value;
                        this.shouldAutofocusUrl = false;
                        this.updatePreview();
                    });
                    this.urlInput.addEventListener('change', () => {
                        const value = this.urlInput.value.trim();
                        this.data.originalUrl = value;
                        this.data.videoId = extractYoutubeId(value);
                        this.data.url = this.data.videoId ? getYoutubeCanonicalUrl(this.data.videoId) : value;
                        this.shouldAutofocusUrl = false;
                        this.updatePreview();
                    });

                    if (this.captionInput) {
                        this.captionInput.addEventListener('input', () => {
                            this.data.caption = this.captionInput.value.trim();
                        });
                    }

                    this.updatePreview();

                    if (!this.readOnly && this.shouldAutofocusUrl && !this.hasAutofocusedUrl) {
                        window.requestAnimationFrame(() => {
                            if (!this.urlInput) {
                                return;
                            }

                            const activeElement = document.activeElement;
                            if (activeElement && activeElement !== document.body && this.wrapper && !this.wrapper.contains(activeElement)) {
                                return;
                            }

                            try {
                                this.urlInput.focus({ preventScroll: true });
                            } catch (focusError) {
                                this.urlInput.focus();
                            }
                            this.urlInput.select();
                            this.hasAutofocusedUrl = true;
                            this.shouldAutofocusUrl = false;
                        });
                    }

                    return this.wrapper;
                }

                updatePreview() {
                    if (!this.previewContainer) {
                        return;
                    }

                    this.previewContainer.innerHTML = '';
                    const urlValue = this.urlInput ? this.urlInput.value.trim() : (this.data.originalUrl || this.data.url || '');
                    const videoId = extractYoutubeId(urlValue);

                    if (videoId) {
                        this.data.videoId = videoId;
                        this.data.embedUrl = getYoutubeEmbedUrl(videoId);
                        this.data.url = getYoutubeCanonicalUrl(videoId);
                        const preview = document.createElement('div');
                        preview.className = 'gta6-youtube-preview';
                        preview.innerHTML = `<iframe src="${this.data.embedUrl}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`;
                        this.previewContainer.appendChild(preview);
                    } else {
                        this.data.videoId = '';
                        this.data.embedUrl = '';
                        this.data.url = urlValue;
                        const placeholder = document.createElement('div');
                        placeholder.className = 'gta6-youtube-placeholder';
                        if (urlValue.length > 0) {
                            placeholder.classList.add('is-error');
                            placeholder.textContent = youtubeStrings.invalidMessage;
                        } else {
                            placeholder.textContent = youtubeStrings.placeholder;
                        }
                        this.previewContainer.appendChild(placeholder);
                    }
                }

                save() {
                    const urlValue = this.urlInput ? this.urlInput.value.trim() : (this.data.originalUrl || this.data.url || '');
                    const captionValue = this.captionInput ? this.captionInput.value.trim() : '';
                    const videoId = extractYoutubeId(urlValue);
                    const canonicalUrl = videoId ? getYoutubeCanonicalUrl(videoId) : urlValue;
                    const embedUrl = videoId ? getYoutubeEmbedUrl(videoId) : '';

                    return {
                        service: videoId ? 'youtube' : '',
                        url: canonicalUrl,
                        originalUrl: urlValue,
                        videoId,
                        embedUrl,
                        caption: captionValue,
                    };
                }

                validate(savedData) {
                    return Boolean(savedData.videoId);
                }
            }

            const initializeEditor = async () => {
                if (typeof EditorJS === 'undefined') {
                    return;
                }

                patchListBackspaceBehavior();

                const tools = {
                    header: {
                        class: window.Header,
                        inlineToolbar: true,
                        config: {
                            placeholder: '<?php echo esc_js( 'Enter a header', 'gta6-mods' ); ?>',
                            levels: [2, 3, 4],
                            defaultLevel: 2
                        }
                    },
                    list: {
                        class: window.List,
                        inlineToolbar: true,
                    },
                    quote: {
                        class: window.Quote,
                        inlineToolbar: true,
                    },
                    delimiter: window.Delimiter,
                    table: {
                        class: window.Table,
                        inlineToolbar: true
                    },
                    underline: window.Underline,
                    embed: {
                        class: window.Embed,
                        inlineToolbar: true,
                        config: {
                            services: {
                                youtube: true
                            }
                        }
                    },
                    youtube: {
                        class: GTA6YoutubeTool,
                    },
                    code: {
                        class: window.CodeTool,
                        placeholder: 'Enter a code snippet',
                    }
                };

                let initialData;
                if (descriptionInput && descriptionInput.value) {
                    try {
                        initialData = JSON.parse(descriptionInput.value);
                    } catch (error) {
                        console.warn('Invalid Editor.js data found in description field.', error);
                    }
                }

                const debouncedSyncEditorData = debounce((apiInstance) => {
                    apiInstance.saver.save()
                        .then((savedData) => {
                            if (descriptionInput) {
                                descriptionInput.value = JSON.stringify(savedData);
                            }
                        })
                        .catch((error) => {
                            console.error('Editor.js saving failed: ', error);
                        });
                }, 350);

                editor = new EditorJS({
                    holder: 'editorjs-container',
                    placeholder: '<?php echo esc_js( 'Provide information and installation instructions...', 'gta6-mods' ); ?>',
                    tools: tools,
                    data: initialData,
                    onChange(apiInstance) {
                        debouncedSyncEditorData(apiInstance);
                        applyInlineToolbarMode();
                    }
                });

                try {
                    await editor.isReady;
                    patchListBackspaceBehavior();
                    if (descriptionInput) {
                        const savedData = await editor.save();
                        descriptionInput.value = JSON.stringify(savedData);
                    }
                    ensureEditorContainerFocusability();
                    observeInlineToolbar();
                    applyInlineToolbarMode();
                    attachBackspaceHandler();
                } catch (error) {
                    console.error('Editor.js initialization failed: ', error);
                }
            };

            initializeEditor();
            window.addEventListener('resize', () => applyInlineToolbarMode());
            window.addEventListener('orientationchange', () => applyInlineToolbarMode());
            window.addEventListener('load', () => applyInlineToolbarMode());


            const applyStickySidebarClasses = () => {
                if (!$) {
                    return;
                }

                $('.theiaStickySidebar').addClass('space-y-6');
            };

            const initializeStickySidebars = () => {
                if (!$ || !$.fn || typeof $.fn.theiaStickySidebar !== 'function') {
                    return;
                }

                const $sidebars = $('.sticky-sidebar');

                if (window.innerWidth >= desktopBreakpoint) {
                    $sidebars.each(function() {
                        const $sidebar = $(this);
                        if (!$sidebar.data('theiaStickySidebar')) {
                            $sidebar.theiaStickySidebar({
                                additionalMarginTop: stickyMarginTop,
                                additionalMarginBottom: stickyMarginBottom,
                                disableOnResponsiveLayouts: true,
                            });
                            applyStickySidebarClasses();
                        } else {
                            $sidebar.theiaStickySidebar('updateSticky');
                            applyStickySidebarClasses();
                        }
                    });
                } else {
                    $sidebars.each(function() {
                        const $sidebar = $(this);
                        if ($sidebar.data('theiaStickySidebar')) {
                            $sidebar.theiaStickySidebar('destroy');
                        }
                    });
                }
            };

            const triggerStickyUpdate = () => {
                if (!$ || !$.fn || typeof $.fn.theiaStickySidebar !== 'function') {
                    return;
                }

                if (window.innerWidth < desktopBreakpoint) {
                    return;
                }

                const $sidebars = $('.sticky-sidebar');
                $sidebars.each(function() {
                    const $sidebar = $(this);
                    if ($sidebar.data('theiaStickySidebar')) {
                        $sidebar.theiaStickySidebar('updateSticky');
                    }
                });
                applyStickySidebarClasses();
            };

            initializeStickySidebars();
            triggerStickyUpdate();
            window.addEventListener('resize', () => {
                initializeStickySidebars();
                triggerStickyUpdate();
            });
            window.addEventListener('orientationchange', () => {
                initializeStickySidebars();
                triggerStickyUpdate();
            });
            window.addEventListener('load', triggerStickyUpdate);

            const categorySelect = document.getElementById('category');
            if (Array.isArray(GTA6ModsUpload.categories) && GTA6ModsUpload.categories.length) {
                GTA6ModsUpload.categories.forEach((cat) => {
                    const option = document.createElement('option');
                    option.value = cat.id;
                    option.dataset.slug = cat.slug;
                    option.textContent = cat.name;
                    categorySelect.appendChild(option);
                });
            } else {
                categorySelect.disabled = true;
                categorySelect.classList.add('bg-gray-100', 'cursor-not-allowed');
                const notice = document.createElement('p');
                notice.className = 'mt-2 text-sm text-red-600';
                notice.textContent = '<?php echo esc_js( __( 'No eligible categories are available. Please contact an administrator.', 'gta6-mods' ) ); ?>';
                categorySelect.parentElement.appendChild(notice);
            }

            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });

            const authorsContainer = document.getElementById('authors-container');
            const addAuthorBtn = document.getElementById('add-author-btn');
            const createAuthorInput = (isDefault = false) => {
                const authorWrapper = document.createElement('div');
                authorWrapper.className = 'flex items-center space-x-2';
                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'authors[]';
                input.className = 'form-input';
                input.placeholder = '<?php echo esc_js( __( 'Enter author name', 'gta6-mods' ) ); ?>';
                if (isDefault) {
                    input.value = GTA6ModsUpload.currentUser?.name || '';
                    input.disabled = true;
                    input.classList.add('bg-gray-100', 'cursor-not-allowed');
                }
                authorWrapper.appendChild(input);

                if (!isDefault) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'text-gray-400 hover:text-red-500 transition-colors';
                    removeBtn.innerHTML = '<i class="fas fa-times-circle"></i>';
                    removeBtn.addEventListener('click', () => authorWrapper.remove());
                    authorWrapper.appendChild(removeBtn);
                }
                authorsContainer.appendChild(authorWrapper);
            };
            createAuthorInput(true);
            addAuthorBtn.addEventListener('click', () => createAuthorInput(false));

            const screenshotInput = document.getElementById('screenshot-upload');
            const previewContainer = document.getElementById('image-preview-container');
            const dropzoneLabel = document.getElementById('dropzone-label');
            let uploadedFiles = [];
            let draggedIndex = null;

            const addFiles = (files) => {
                const allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                const filesArray = Array.from(files);
                const imageFiles = [];
                let rejected = false;

                filesArray.forEach(file => {
                    const extension = file.name.split('.').pop().toLowerCase();
                    const isImageType = file.type ? file.type.startsWith('image/') : true;
                    if (!isImageType) {
                        return;
                    }
                    if (!allowedImageExtensions.includes(extension)) {
                        rejected = true;
                        return;
                    }
                    imageFiles.push(file);
                });

                if (rejected) {
                    alert('<?php echo esc_js( __( 'Only JPG, PNG, and WEBP screenshots are allowed.', 'gta6-mods' ) ); ?>');
                }

                if (!imageFiles.length) {
                    screenshotInput.value = '';
                    return;
                }

                const wasEmpty = uploadedFiles.length === 0;
                imageFiles.forEach(file => {
                    if (file.size > 10 * 1024 * 1024) {
                        alert('<?php echo esc_js( __( 'Images must be smaller than 10MB.', 'gta6-mods' ) ); ?>');
                        return;
                    }
                    const url = URL.createObjectURL(file);
                    uploadedFiles.push({ file, url, isFeatured: false });
                });
                if (wasEmpty && uploadedFiles.length > 0) {
                    uploadedFiles[0].isFeatured = true;
                }
                renderPreviews();
                screenshotInput.value = '';
            };

            screenshotInput.addEventListener('change', (e) => addFiles(e.target.files));
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => dropzoneLabel.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }));
            ['dragenter', 'dragover'].forEach(evt => dropzoneLabel.addEventListener(evt, () => dropzoneLabel.classList.add('bg-pink-50', 'border-pink-400')));
            ['dragleave', 'drop'].forEach(evt => dropzoneLabel.addEventListener(evt, () => dropzoneLabel.classList.remove('bg-pink-50', 'border-pink-400')));
            dropzoneLabel.addEventListener('drop', e => addFiles(e.dataTransfer.files));

            const renderPreviews = () => {
                previewContainer.innerHTML = '';
                uploadedFiles.forEach((item, index) => {
                    const previewWrapper = document.createElement('div');
                    previewWrapper.className = 'relative group aspect-video rounded-lg cursor-grab w-[calc(50%-0.5rem)]';
                    previewWrapper.setAttribute('draggable', true);
                    previewWrapper.dataset.index = index;
                    const img = document.createElement('img');
                    img.src = item.url;
                    img.alt = item.file.name;
                    img.className = 'w-full h-full object-cover rounded-md pointer-events-none';
                    const numberBadge = document.createElement('span');
                    numberBadge.className = 'absolute top-2 left-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white text-xs font-bold z-10 pointer-events-none';
                    numberBadge.textContent = index + 1;
                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'absolute top-2 right-2 w-7 h-7 flex items-center justify-center bg-black/60 rounded-full text-white hover:bg-red-500 transition-colors z-10';
                    deleteBtn.innerHTML = '<i class="fas fa-times text-sm"></i>';
                    deleteBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const wasFeatured = item.isFeatured;
                        URL.revokeObjectURL(item.url);
                        uploadedFiles.splice(index, 1);
                        if (wasFeatured && uploadedFiles.length > 0) {
                            uploadedFiles[0].isFeatured = true;
                        }
                        renderPreviews();
                    });
                    const radioLabel = document.createElement('label');
                    radioLabel.className = 'absolute bottom-2 right-2 flex items-center p-1.5 bg-black/60 rounded-full cursor-pointer text-white text-xs backdrop-blur-sm transition-all';
                    const radioInput = document.createElement('input');
                    radioInput.type = 'radio';
                    radioInput.name = 'featured_image';
                    radioInput.checked = item.isFeatured;
                    radioInput.className = 'hidden peer';
                    radioInput.addEventListener('change', () => {
                        uploadedFiles.forEach((x, idx) => (x.isFeatured = (idx === index)));
                        renderPreviews();
                    });
                    const customRadio = document.createElement('span');
                    customRadio.className = 'w-4 h-4 rounded-full border-2 border-white flex-shrink-0 mr-1.5 peer-checked:bg-pink-500 peer-checked:border-pink-500 transition-colors duration-200';
                    const labelText = document.createTextNode('<?php echo esc_js( __( 'Featured', 'gta6-mods' ) ); ?>');
                    radioLabel.appendChild(radioInput);
                    radioLabel.appendChild(customRadio);
                    radioLabel.appendChild(labelText);
                    previewWrapper.appendChild(img);
                    previewWrapper.appendChild(numberBadge);
                    previewWrapper.appendChild(deleteBtn);
                    previewWrapper.appendChild(radioLabel);
                    if (item.isFeatured) {
                        previewWrapper.classList.add('ring-2', 'ring-pink-500', 'ring-offset-2', 'ring-offset-white');
                    }
                    previewContainer.appendChild(previewWrapper);
                });
                triggerStickyUpdate();
            };

            const ensureFeaturedFirst = () => {
                if (uploadedFiles.length === 0) {
                    return false;
                }

                const featuredIndex = uploadedFiles.findIndex(item => item.isFeatured);

                if (featuredIndex === -1) {
                    uploadedFiles.forEach((item, index) => {
                        item.isFeatured = (index === 0);
                    });
                    return true;
                }

                if (featuredIndex > 0) {
                    const [featuredItem] = uploadedFiles.splice(featuredIndex, 1);
                    uploadedFiles.unshift(featuredItem);
                    uploadedFiles.forEach((item, index) => {
                        item.isFeatured = (index === 0);
                    });
                    return true;
                }

                return false;
            };

            previewContainer.addEventListener('dragstart', e => {
                if (e.target.dataset.index) {
                    draggedIndex = parseInt(e.target.dataset.index, 10);
                    e.dataTransfer.effectAllowed = 'move';
                    setTimeout(() => e.target.classList.add('dragging'), 0);
                }
            });
            previewContainer.addEventListener('dragend', e => {
                if (draggedIndex !== null) {
                    e.target.classList.remove('dragging');
                    draggedIndex = null;
                    document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                }
            });
            previewContainer.addEventListener('dragover', e => {
                e.preventDefault();
                const target = e.target.closest('[draggable="true"]');
                document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                if (target && target.dataset.index !== undefined && parseInt(target.dataset.index, 10) !== draggedIndex) {
                    target.classList.add('drag-over');
                }
            });
            previewContainer.addEventListener('drop', e => {
                e.preventDefault();
                document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                const target = e.target.closest('[draggable="true"]');
                if (target && draggedIndex !== null) {
                    const targetIndex = parseInt(target.dataset.index, 10);
                    const [draggedItem] = uploadedFiles.splice(draggedIndex, 1);
                    uploadedFiles.splice(targetIndex, 0, draggedItem);
                    renderPreviews();
                }
            });

            const allNavLinks = document.querySelectorAll('.header-nav-bar nav a');
            const updateActiveIcon = (categoryValue) => {
                allNavLinks.forEach(link => {
                    link.classList.remove('category-active', 'category-dimmed');
                });
                if (!categoryValue) {
                    return;
                }
                allNavLinks.forEach(link => {
                    if (link.dataset.category === categoryValue) {
                        link.classList.add('category-active');
                    } else {
                        link.classList.add('category-dimmed');
                    }
                });
            };

            categorySelect.addEventListener('change', (event) => {
                const selectedOption = event.target.selectedOptions[0];
                updateActiveIcon(selectedOption ? selectedOption.dataset.slug : '');
            });

            allNavLinks.forEach(link => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (link.dataset.disabled === 'true') {
                        return;
                    }
                    if (!formStep3.classList.contains('hidden')) {
                        return;
                    }
                    const slug = link.dataset.category;
                    const matchingOption = Array.from(categorySelect.options).find(opt => opt.dataset.slug === slug);
                    if (matchingOption) {
                        categorySelect.value = matchingOption.value;
                        updateActiveIcon(slug);
                    }
                });
            });

            const formStep1 = document.getElementById('form-step-1');
            const formStep2 = document.getElementById('form-step-2');
            const formStep3 = document.getElementById('form-step-3');
            const mainTitle = document.getElementById('main-title');
            const formCard = document.getElementById('form-card');
            const uploadRules = document.getElementById('upload-rules');
            const continueBtn = document.getElementById('continue-btn');
            const backBtn = document.getElementById('back-btn');
            const previewBtn = document.getElementById('preview-btn');
            const backToStep2Btn = document.getElementById('back-to-step-2-btn');
            const modFileInput = document.getElementById('mod-file-input');
            const modUrlInput = document.getElementById('mod-url-input');
            const fileSizeInput = document.getElementById('file-size-input');
            const fileSizeUnit = document.getElementById('file-size-unit');
            const fileUploadView = document.getElementById('file-upload-view');
            const urlView = document.getElementById('url-view');
            const showUrlViewBtn = document.getElementById('show-url-view-btn');
            const showFileViewBtn = document.getElementById('show-file-view-btn');
            const step1ErrorsContainer = document.getElementById('step-1-errors');
            const step2ErrorsContainer = document.getElementById('step-2-errors');
            const previewBanner = document.getElementById('preview-mode-banner');
            const previewHeader = document.getElementById('preview-header');

            continueBtn.addEventListener('click', async () => {
                step1ErrorsContainer.innerHTML = '';
                const errors = [];
                if (document.getElementById('file-name').value.trim() === '') {
                    errors.push('<?php echo esc_js( __( 'The "File Name" field is required.', 'gta6-mods' ) ); ?>');
                }
                if (!categorySelect.value) {
                    if (categorySelect.disabled) {
                        errors.push('<?php echo esc_js( __( 'No upload categories are configured on this site.', 'gta6-mods' ) ); ?>');
                    } else {
                        errors.push('<?php echo esc_js( __( 'Please select a category.', 'gta6-mods' ) ); ?>');
                    }
                }
                if(editor) {
                    const savedData = await editor.save();
                    if (savedData.blocks.length === 0) {
                        errors.push('<?php echo esc_js( __( 'The description cannot be empty.', 'gta6-mods' ) ); ?>');
                    }
                }
                if (uploadedFiles.length === 0) {
                    errors.push('<?php echo esc_js( __( 'Please upload at least one screenshot.', 'gta6-mods' ) ); ?>');
                }

                if (errors.length > 0) {
                    const errorList = document.createElement('ul');
                    errorList.className = 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg';
                    errors.forEach(errorText => {
                        const listItem = document.createElement('li');
                        listItem.textContent = errorText;
                        errorList.appendChild(listItem);
                    });
                    step1ErrorsContainer.appendChild(errorList);
                } else {
                    const didReorder = ensureFeaturedFirst();
                    if (didReorder) {
                        renderPreviews();
                    }
                    formStep1.classList.add('hidden');
                    formStep2.classList.remove('hidden');
                    window.scrollTo(0, 0);
                }
            });

            backBtn.addEventListener('click', () => {
                formStep2.classList.add('hidden');
                formStep1.classList.remove('hidden');
                window.scrollTo(0, 0);
            });

            previewBtn.addEventListener('click', () => {
                step2ErrorsContainer.innerHTML = '';
                const errors = [];
                const isUrlMode = !urlView.classList.contains('hidden');

                if (isUrlMode) {
                    const urlValue = modUrlInput.value.trim();
                    try {
                        if (urlValue === '') {
                            throw new Error('empty');
                        }
                        // eslint-disable-next-line no-new
                        new URL(urlValue);
                    } catch (err) {
                        errors.push('<?php echo esc_js( __( 'Please provide a valid download URL.', 'gta6-mods' ) ); ?>');
                    }

                    const sizeValue = parseFloat(fileSizeInput.value);
                    if (Number.isNaN(sizeValue) || sizeValue <= 0) {
                        errors.push('<?php echo esc_js( __( 'Please enter a valid file size.', 'gta6-mods' ) ); ?>');
                    }
                } else if (modFileInput.files.length === 0) {
                    errors.push('<?php echo esc_js( __( 'Please select a mod file to upload.', 'gta6-mods' ) ); ?>');
                }

                if (document.getElementById('version').value.trim() === '') {
                    errors.push('<?php echo esc_js( __( 'The "Version" field is required.', 'gta6-mods' ) ); ?>');
                }
                if (errors.length > 0) {
                    const errorList = document.createElement('ul');
                    errorList.className = 'list-disc list-inside text-sm text-red-600 bg-red-100 p-4 rounded-lg';
                    errors.forEach(errorText => {
                        const listItem = document.createElement('li');
                        listItem.textContent = errorText;
                        errorList.appendChild(listItem);
                    });
                    step2ErrorsContainer.appendChild(errorList);
                } else {
                    populatePreview();
                    formStep2.classList.add('hidden');
                    mainTitle.classList.add('hidden');
                    uploadRules.classList.add('hidden');
                    formCard.classList.remove('p-6', 'md:p-8', 'card');
                    previewBanner.classList.remove('hidden');
                    formStep3.classList.remove('hidden');
                    window.scrollTo(0, 0);
                }
            });

            backToStep2Btn.addEventListener('click', () => {
                formStep3.classList.add('hidden');
                previewBanner.classList.add('hidden');
                mainTitle.classList.remove('hidden');
                uploadRules.classList.remove('hidden');
                formCard.classList.add('p-6', 'md:p-8', 'card');
                formStep2.classList.remove('hidden');
                window.scrollTo(0, 0);
            });

            const modDropzoneLabel = document.getElementById('mod-dropzone-label');
            const modDropzoneContent = document.getElementById('mod-dropzone-content');
            const modFilePreview = document.getElementById('mod-file-preview');

            const handleModFile = (files) => {
                if (files && files.length > 0) {
                    const file = files[0];
                    const allowedExtensions = ['zip','rar','7z','oiv'];
                    const extension = file.name.split('.').pop().toLowerCase();
                    if (!allowedExtensions.includes(extension)) {
                        alert('<?php echo esc_js( __( 'Invalid file type. Allowed: zip, rar, 7z, oiv.', 'gta6-mods' ) ); ?>');
                        return;
                    }
                    if (file.size > 400 * 1024 * 1024) {
                        alert('<?php echo esc_js( __( 'File must be smaller than 400MB.', 'gta6-mods' ) ); ?>');
                        return;
                    }
                    modFileInput.files = files;
                    modDropzoneContent.classList.add('hidden');
                    modFilePreview.innerHTML = `
                        <div class="flex flex-col items-center">
                           <i class="fas fa-check-circle text-green-500 text-4xl"></i>
                           <p class="font-semibold mt-2 break-all">${file.name}</p>
                           <p class="text-sm text-gray-500">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                           <button type="button" id="remove-mod-file-btn" class="mt-2 text-sm font-semibold text-red-600 hover:text-red-800 transition"><?php echo esc_js( __( 'Remove', 'gta6-mods' ) ); ?></button>
                        </div>
                    `;
                    modFilePreview.classList.remove('hidden');
                    modFilePreview.classList.add('flex');

                    document.getElementById('remove-mod-file-btn').addEventListener('click', (e) => {
                        e.preventDefault();
                        modFileInput.value = '';
                        modFilePreview.classList.add('hidden');
                        modFilePreview.classList.remove('flex');
                        modDropzoneContent.classList.remove('hidden');
                    });
                } else {
                    modFileInput.value = '';
                    modFilePreview.classList.add('hidden');
                    modFilePreview.classList.remove('flex');
                    modDropzoneContent.classList.remove('hidden');
                }
            };

            modFileInput.addEventListener('change', () => handleModFile(modFileInput.files));
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => modDropzoneLabel.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }));
            ['dragenter', 'dragover'].forEach(evt => modDropzoneLabel.addEventListener(evt, () => modDropzoneLabel.classList.add('bg-pink-50', 'border-pink-400')));
            ['dragleave', 'drop'].forEach(evt => modDropzoneLabel.addEventListener(evt, () => modDropzoneLabel.classList.remove('bg-pink-50', 'border-pink-400')));
            modDropzoneLabel.addEventListener('drop', e => handleModFile(e.dataTransfer.files));

            showUrlViewBtn.addEventListener('click', () => {
                fileUploadView.classList.add('hidden');
                urlView.classList.remove('hidden');
                handleModFile(null);
            });

            showFileViewBtn.addEventListener('click', () => {
                urlView.classList.add('hidden');
                fileUploadView.classList.remove('hidden');
                modUrlInput.value = '';
                fileSizeInput.value = '';
                if (fileSizeUnit) {
                    fileSizeUnit.value = 'MB';
                }
            });

            const populatePreview = async () => {
                const categoryElement = document.getElementById('category');
                const selectedOption = categoryElement.selectedOptions[0];
                const categoryText = selectedOption ? selectedOption.textContent : '';
                const fileName = document.getElementById('file-name').value;
                const version = document.getElementById('version').value;
                const authorInputs = document.querySelectorAll('input[name="authors[]"]');
                const authorNames = Array.from(authorInputs).map(input => input.value).filter(Boolean).join(', ');

                previewHeader.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <h1 class="text-2xl md:text-4xl font-bold text-gray-900">${fileName}</h1>
                        <span class="text-xl md:text-2xl font-semibold text-gray-400">${version}</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-500 mt-1">
                        <span>by</span>
                        <span class="font-semibold text-pink-600 ml-1">${authorNames}</span>
                    </div>
                `;

                if(editor) {
                    const savedData = await editor.save();
                    const descriptionJSON = JSON.stringify(savedData);
                    document.getElementById('preview-description-content').innerHTML = gta6_mods_render_editorjs_data_preview(descriptionJSON);
                }

                document.getElementById('preview-sidebar-authors').textContent = authorNames;
                document.getElementById('preview-sidebar-version').textContent = version;
                document.getElementById('preview-sidebar-updated').textContent = new Date().toLocaleDateString('<?php echo esc_js( str_replace( '_', '-', get_locale() ) ); ?>', { year: 'numeric', month: 'long', day: 'numeric' });
                document.getElementById('preview-sidebar-category').textContent = categoryText;

                const sourceInfoContainer = document.getElementById('preview-sidebar-source-info');
                sourceInfoContainer.innerHTML = '';
                const isUrlMode = !urlView.classList.contains('hidden');

                if (isUrlMode) {
                    const trimmedUrl = modUrlInput.value.trim();
                    const sizeValue = fileSizeInput.value ? parseFloat(fileSizeInput.value) : 0;
                    const formattedSize = Number.isNaN(sizeValue) ? '' : sizeValue.toFixed(sizeValue % 1 === 0 ? 0 : 2).replace(/\.00$/, '');
                    const unit = fileSizeUnit ? fileSizeUnit.value : 'MB';

                    sourceInfoContainer.innerHTML = `
                        <p class="preview-value font-mono text-sm bg-gray-200 p-2 rounded break-words">${trimmedUrl}</p>
                        <p class="text-right text-sm text-gray-600 mt-1"><?php echo esc_js( __( 'Size', 'gta6-mods' ) ); ?>: <strong>${formattedSize ? formattedSize : fileSizeInput.value} ${unit}</strong></p>
                    `;
                } else if (modFileInput.files.length > 0) {
                    const file = modFileInput.files[0];
                    const sizeInMb = (file.size / 1024 / 1024).toFixed(2);
                    sourceInfoContainer.innerHTML = `
                        <p class="preview-value font-mono text-sm bg-gray-200 p-2 rounded break-words">${file.name}</p>
                        <p class="text-right text-sm text-gray-600 mt-1"><?php echo esc_js( __( 'Size', 'gta6-mods' ) ); ?>: <strong>${sizeInMb} MB</strong></p>
                    `;
                } else {
                    sourceInfoContainer.innerHTML = `<p class="text-sm text-gray-500 italic"><?php echo esc_js( __( 'No file selected', 'gta6-mods' ) ); ?></p>`;
                }

                const tagsContainer = document.getElementById('preview-sidebar-tags');
                tagsContainer.innerHTML = '';
                const tags = document.getElementById('tags').value.split(',').map(tag => tag.trim()).filter(tag => tag !== '');
                if (tags.length > 0) {
                    tags.forEach(tag => {
                        const tagElement = document.createElement('span');
                        tagElement.className = 'bg-gray-200 text-gray-700 text-xs font-semibold px-2.5 py-1 rounded-full';
                        tagElement.textContent = tag;
                        tagsContainer.appendChild(tagElement);
                    });
                } else {
                    tagsContainer.textContent = '<?php echo esc_js( __( 'No tags provided.', 'gta6-mods' ) ); ?>';
                }

                const galleryContainer = document.getElementById('preview-gallery-container');
                const featuredImageContainer = galleryContainer.querySelector('.aspect-video');
                const thumbnailsContainer = document.getElementById('preview-gallery-thumbnails');
                const loadMoreContainer = document.getElementById('preview-load-more-container');

                if (pswpLightbox) {
                    pswpLightbox.destroy();
                    pswpLightbox = null;
                }

                featuredImageContainer.innerHTML = '';
                thumbnailsContainer.innerHTML = '';
                loadMoreContainer.innerHTML = '';

                if (uploadedFiles.length === 0) return;

                const getImageDimensions = (fileData) => {
                    return new Promise((resolve, reject) => {
                        const img = new Image();
                        img.onload = () => resolve({
                            ...fileData,
                            width: img.naturalWidth,
                            height: img.naturalHeight
                        });
                        img.onerror = reject;
                        img.src = fileData.url;
                    });
                };

                Promise.all(uploadedFiles.map(getImageDimensions))
                    .then(imagesWithDimensions => {
                        const featuredFile = imagesWithDimensions.find(f => f.isFeatured) || imagesWithDimensions[0];
                        const otherImages = imagesWithDimensions.filter(f => f !== featuredFile);

                        if (featuredFile) {
                            const featuredLink = document.createElement('a');
                            featuredLink.href = featuredFile.url;
                            featuredLink.dataset.pswpWidth = featuredFile.width;
                            featuredLink.dataset.pswpHeight = featuredFile.height;
                            featuredLink.className = 'gallery-item block w-full h-full';

                            const featuredImg = document.createElement('img');
                            featuredImg.src = featuredFile.url;
                            featuredImg.alt = 'Featured Screenshot';
                            featuredImg.className = 'w-full h-full object-cover';
                            featuredLink.appendChild(featuredImg);
                            featuredImageContainer.appendChild(featuredLink);
                        }

                        otherImages.forEach((fileData, index) => {
                            const thumbLink = document.createElement('a');
                            thumbLink.href = fileData.url;
                            thumbLink.dataset.pswpWidth = fileData.width;
                            thumbLink.dataset.pswpHeight = fileData.height;
                            thumbLink.className = 'gallery-item relative aspect-video block';

                            if (index >= 5) {
                                thumbLink.classList.add('hidden', 'extra-thumbnail');
                            }

                            const thumbImg = document.createElement('img');
                            thumbImg.src = fileData.url;
                            thumbImg.className = 'w-full h-full object-cover rounded-lg';

                            thumbLink.appendChild(thumbImg);
                            thumbnailsContainer.appendChild(thumbLink);
                        });

                        if (otherImages.length > 5) {
                            const loadMoreBtn = document.createElement('button');
                            loadMoreBtn.type = 'button';
                            loadMoreBtn.className = 'w-full py-2 px-4 rounded-lg border-2 border-pink-500 text-pink-600 font-semibold hover:bg-pink-50 transition duration-300 ease-in-out';
                            loadMoreBtn.innerHTML = `<i class="fas fa-images mr-2"></i><?php echo esc_js( __( 'Load more images', 'gta6-mods' ) ); ?> (${otherImages.length - 5} <?php echo esc_js( __( 'more', 'gta6-mods' ) ); ?>)`;
                            loadMoreBtn.addEventListener('click', () => {
                                document.querySelectorAll('.extra-thumbnail').forEach(el => el.classList.remove('hidden'));
                                loadMoreBtn.remove();
                            });
                            loadMoreContainer.appendChild(loadMoreBtn);
                        }

                        pswpLightbox = new PhotoSwipeLightbox({
                            gallery: '#preview-gallery-container',
                            children: 'a.gallery-item',
                            pswpModule: () => import('https://unpkg.com/photoswipe@5/dist/photoswipe.esm.js'),
                        });

                        pswpLightbox.init();
                    })
                    .catch(error => {
                        console.error('Error loading image for preview:', error);
                    });
            };

            if (uploadForm) {
                uploadForm.addEventListener('submit', async (e) => {
                    e.preventDefault();

                    if (submissionErrors) {
                        submissionErrors.textContent = '';
                    }

                    if (editor) {
                        try {
                            const savedData = await editor.save();
                            if (descriptionInput) {
                                descriptionInput.value = JSON.stringify(savedData);
                            }
                        } catch (error) {
                            console.error('Unable to save Editor.js data before submission:', error);
                            if (submissionErrors) {
                                submissionErrors.textContent = '<?php echo esc_js( __( 'We could not prepare your description for upload. Please try again.', 'gta6-mods' ) ); ?>';
                            }
                            return;
                        }
                    }

                    const overlay = document.getElementById('uploading-overlay');
                    const statusText = document.getElementById('uploading-status');
                    const titleText = document.getElementById('uploading-title');
                    const spinner = document.getElementById('uploading-spinner');
                    const progressContainer = document.getElementById('progress-container');
                    const progressBar = document.getElementById('upload-progress-bar');
                    const progressText = document.getElementById('upload-progress-text');
                    const speedText = document.getElementById('upload-speed-text');
                    const etaText = document.getElementById('upload-eta-text');

                    progressBar.style.width = '0%';
                    progressText.textContent = '0%';
                    speedText.textContent = '';
                    etaText.textContent = '';
                    progressContainer.classList.add('hidden');
                    spinner.classList.remove('hidden');
                    titleText.textContent = 'Uploading your mod...';
                    statusText.textContent = 'Please wait while we process your files.';
                    overlay.classList.remove('hidden');

                    const formData = new FormData();
                formData.append('action', 'gta6mods_submit_mod_upload');
                formData.append('nonce', GTA6ModsUpload.nonce);
                formData.append('file_name', document.getElementById('file-name').value);
                formData.append('category_id', categorySelect.value);
                formData.append('tags', document.getElementById('tags').value);
                    formData.append('description', descriptionInput ? descriptionInput.value : '');
                formData.append('version', document.getElementById('version').value);
                formData.append('video_permissions', document.querySelector('input[name="video-permissions"]:checked').value);

                const additionalAuthors = Array.from(document.querySelectorAll('#authors-container input:not([disabled])'))
                    .map(input => input.value.trim())
                    .filter(Boolean);
                additionalAuthors.forEach(author => formData.append('additional_authors[]', author));

                const featuredIndex = uploadedFiles.findIndex(item => item.isFeatured);
                const validIndex = (featuredIndex >= 0 && featuredIndex < uploadedFiles.length) ? featuredIndex : 0;
                formData.append('featured_index', validIndex);

                uploadedFiles.forEach((item) => {
                    formData.append('screenshots[]', item.file, item.file.name);
                });

                const isUrlMode = !urlView.classList.contains('hidden');

                if (isUrlMode) {
                    formData.append('mod_external_url', modUrlInput.value.trim());
                    formData.append('mod_external_size_value', fileSizeInput.value.trim());
                    formData.append('mod_external_size_unit', fileSizeUnit ? fileSizeUnit.value : 'MB');
                } else if (modFileInput.files.length > 0) {
                    formData.append('mod_file', modFileInput.files[0], modFileInput.files[0].name);
                }

                const xhr = new XMLHttpRequest();
                let lastLoaded = 0;
                let lastTime = new Date().getTime();

                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        const currentTime = new Date().getTime();
                        const deltaTime = (currentTime - lastTime) / 1000;
                        const deltaLoaded = event.loaded - lastLoaded;

                        if (deltaTime > 0) {
                            const speed = deltaLoaded / deltaTime;
                            const speedMBps = (speed / 1024 / 1024).toFixed(2);
                            speedText.textContent = `${speedMBps} MB/s`;

                            const bytesRemaining = event.total - event.loaded;
                            const timeRemaining = bytesRemaining / speed;

                            if (isFinite(timeRemaining)) {
                                let etaString;
                                if (timeRemaining > 60) {
                                    etaString = `approx. ${Math.round(timeRemaining / 60)} min`;
                                } else {
                                    etaString = `approx. ${Math.round(timeRemaining)} sec`;
                                }
                                etaText.textContent = `ETA: ${etaString}`;
                            } else {
                                etaText.textContent = '...';
                            }
                        }
                        
                        lastLoaded = event.loaded;
                        lastTime = currentTime;
                        const percentComplete = Math.round((event.loaded / event.total) * 100);
                        progressBar.style.width = percentComplete + '%';
                        progressText.textContent = percentComplete + '%';
                        
                        const loadedSize = (event.loaded / 1024 / 1024).toFixed(2);
                        const totalSize = (event.total / 1024 / 1024).toFixed(2);
                        statusText.textContent = `Uploaded: ${loadedSize} MB / ${totalSize} MB`;

                        if (progressContainer.classList.contains('hidden')) {
                            spinner.classList.add('hidden');
                            progressContainer.classList.remove('hidden');
                            titleText.textContent = 'Uploading...';
                        }
                    }
                });

                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (!result.success) {
                                throw new Error(result?.data?.message || 'Something went wrong during upload.');
                            }
                            titleText.textContent = 'Upload successful!';
                            statusText.textContent = 'Creating file data sheet...';
                            speedText.textContent = '';
                            etaText.textContent = '';
                            
                            setTimeout(() => {
                                window.location.href = result.data.redirect_url;
                            }, 800);
                        } catch(e) {
                           console.error('Error parsing response:', e, xhr.responseText);
                           overlay.classList.add('hidden');
                           submissionErrors.textContent = 'Invalid response from server.';
                        }
                    } else {
                        console.error('Request failed with status:', xhr.status, xhr.responseText);
                        overlay.classList.add('hidden');
                        submissionErrors.textContent = `Upload failed. Server responded with status ${xhr.status}`;
                    }
                });

                xhr.addEventListener('error', () => {
                    console.error('Upload Error');
                    overlay.classList.add('hidden');
                    submissionErrors.textContent = 'An error occurred during the upload. Please check your network connection.';
                });

                xhr.open('POST', GTA6ModsUpload.ajax_url, true);
                xhr.send(formData);
            });
            }

            const gta6_mods_render_editorjs_data_preview = (json_data) => {
                const data = JSON.parse(json_data);
                if (!data || !Array.isArray(data.blocks)) return '';

                let html = '';
                data.blocks.forEach(block => {
                    switch (block.type) {
                        case 'header':
                            html += `<h${block.data.level}>${block.data.text}</h${block.data.level}>`;
                            break;
                        case 'paragraph':
                            html += `<p>${block.data.text}</p>`;
                            break;
                        case 'list':
                            const tag = block.data.style === 'ordered' ? 'ol' : 'ul';
                            html += `<${tag}>${block.data.items.map(item => `<li>${item}</li>`).join('')}</${tag}>`;
                            break;
                        case 'quote':
                            html += `<blockquote><p>${block.data.text}</p>${block.data.caption ? `<footer>${block.data.caption}</footer>` : ''}</blockquote>`;
                            break;
                        case 'delimiter':
                            html += '<hr>';
                            break;
                        case 'table':
                            if (Array.isArray(block.data.content) && block.data.content.length) {
                                const rows = block.data.content.map(row => Array.isArray(row) ? row.slice() : []);
                                html += '<table>';
                                if (block.data.withHeadings) {
                                    const headerRow = rows.shift() || [];
                                    html += `<thead><tr>${headerRow.map(cell => `<th>${cell}</th>`).join('')}</tr></thead>`;
                                }
                                html += `<tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
                            }
                            break;
                        case 'embed':
                            if (block.data.service === 'youtube' && block.data.embed) {
                                html += `<div class="gta6-editor-embed"><iframe src="${block.data.embed}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>`;
                                if (block.data.caption) {
                                    html += `<p class="text-center text-sm text-gray-500 italic mt-2">${block.data.caption}</p>`;
                                }
                            }
                            break;
                        case 'youtube':
                            {
                                const embedUrl = block.data.embedUrl || getYoutubeEmbedUrl(block.data.videoId || extractYoutubeId(block.data.url || ''));
                                if (embedUrl) {
                                    html += `<div class="gta6-editor-embed"><iframe src="${embedUrl}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>`;
                                    if (block.data.caption) {
                                        html += `<p class="text-center text-sm text-gray-500 italic mt-2">${block.data.caption}</p>`;
                                    }
                                }
                            }
                            break;
                        case 'code':
                            html += `<pre><code class="language-plaintext">${block.data.code.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</code></pre>`;
                            break;
                    }
                });
                return html;
            }
        });
    </script>
<?php get_footer(); ?>

