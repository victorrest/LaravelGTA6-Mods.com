<?php
/**
 * Template Name: Forum Create Thread
 */

declare(strict_types=1);

get_header();

$forum_home_url = function_exists('gta6_forum_get_main_url') ? gta6_forum_get_main_url() : home_url('/forum/');
$valid_thread_types = ['text', 'image', 'link'];
$requested_type = isset($_GET['type']) ? sanitize_key(wp_unslash((string) $_GET['type'])) : '';
$initial_tab = in_array($requested_type, $valid_thread_types, true) ? $requested_type : 'text';
$is_text_active  = 'text' === $initial_tab;
$is_image_active = 'image' === $initial_tab;
$is_link_active  = 'link' === $initial_tab;
?>

<main class="container mx-auto px-4 lg:px-6 mt-8 forum-create" data-forum-create data-initial-tab="<?php echo esc_attr($initial_tab); ?>">
    <div class="forum-create__intro">
        <h1 class="forum-create__headline"><?php echo esc_html__('Create a new thread', 'gta6mods'); ?></h1>
        <p class="forum-create__lede"><?php echo esc_html__('Share discoveries, showcase mods, or ask the community for help.', 'gta6mods'); ?></p>
    </div>

    <div class="grid grid-cols-12 gap-6 forum-create__grid">
        <div class="col-span-12 lg:col-span-8 forum-create__primary">
            <div class="card forum-create__card">
                <div class="flex border-b border-gray-200 dark:border-gray-600 forum-create__tabs" role="tablist">
                    <button type="button" class="forum-tab-button<?php echo $is_text_active ? ' active' : ''; ?>" data-tab="text" role="tab">
                        <i class="fas fa-file-alt"></i>
                        <span><?php echo esc_html__('Text', 'gta6mods'); ?></span>
                    </button>
                    <button type="button" class="forum-tab-button<?php echo $is_image_active ? ' active' : ''; ?>" data-tab="image" role="tab">
                        <i class="fas fa-image"></i>
                        <span><?php echo esc_html__('Image', 'gta6mods'); ?></span>
                    </button>
                    <button type="button" class="forum-tab-button<?php echo $is_link_active ? ' active' : ''; ?>" data-tab="link" role="tab">
                        <i class="fas fa-link"></i>
                        <span><?php echo esc_html__('Link', 'gta6mods'); ?></span>
                    </button>
                </div>

                <form class="forum-create__form" data-create-form enctype="multipart/form-data">
                    <div class="forum-create__panel<?php echo $is_text_active ? '' : ' hidden'; ?>" data-tab-panel="text" aria-hidden="<?php echo $is_text_active ? 'false' : 'true'; ?>">
                        <div class="forum-field">
                            <label for="forum-title-text" class="forum-label"><?php echo esc_html__('Title*', 'gta6mods'); ?></label>
                            <input type="text" id="forum-title-text" class="forum-input" data-field="title" placeholder="<?php echo esc_attr__('Make it descriptive…', 'gta6mods'); ?>">
                        </div>

                        <div class="forum-field">
                            <label for="forum-mod-url-text" class="forum-label"><?php echo esc_html__('Related mod URL (optional)', 'gta6mods'); ?></label>
                            <div class="forum-input-icon">
                                <i class="fas fa-link"></i>
                                <input type="url" id="forum-mod-url-text" class="forum-input" data-field="mod-url" placeholder="https://www.gta6-mods.com/vehicles/...">
                            </div>
                        </div>

                        <div class="forum-field">
                            <label for="forum-content" class="forum-label"><?php echo esc_html__('Body', 'gta6mods'); ?></label>
                            <div class="forum-editor" data-editor>
                                <div class="forum-editor__toolbar" data-editor-toolbar>
                                    <button type="button" class="forum-editor__button" data-editor-action="bold" aria-label="<?php echo esc_attr__('Bold', 'gta6mods'); ?>"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="forum-editor__button" data-editor-action="italic" aria-label="<?php echo esc_attr__('Italic', 'gta6mods'); ?>"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="forum-editor__button" data-editor-action="link" aria-label="<?php echo esc_attr__('Link', 'gta6mods'); ?>"><i class="fas fa-link"></i></button>
                                <button type="button" class="forum-editor__button" data-editor-action="code" aria-label="<?php echo esc_attr__('Code', 'gta6mods'); ?>"><i class="fas fa-code"></i></button>
                                <button type="button" class="forum-editor__button" data-editor-action="img" aria-label="<?php echo esc_attr__('Insert image', 'gta6mods'); ?>"><i class="fas fa-image"></i></button>
                                <button type="button" class="forum-editor__button" data-editor-action="ol" aria-label="<?php echo esc_attr__('Ordered list', 'gta6mods'); ?>"><i class="fas fa-list-ol"></i></button>
                                <button type="button" class="forum-editor__button" data-editor-action="ul" aria-label="<?php echo esc_attr__('Unordered list', 'gta6mods'); ?>"><i class="fas fa-list-ul"></i></button>
                                </div>
                                <textarea id="forum-content" class="forum-textarea" data-field="content" rows="10" placeholder="<?php echo esc_attr__('Tell the community what is on your mind…', 'gta6mods'); ?>"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="forum-create__panel<?php echo $is_image_active ? '' : ' hidden'; ?>" data-tab-panel="image" aria-hidden="<?php echo $is_image_active ? 'false' : 'true'; ?>">
                        <div class="forum-field">
                            <label for="forum-title-image" class="forum-label"><?php echo esc_html__('Title*', 'gta6mods'); ?></label>
                            <input type="text" id="forum-title-image" class="forum-input" data-field="title" placeholder="<?php echo esc_attr__('Give your screenshot a title…', 'gta6mods'); ?>">
                        </div>

                        <div class="forum-field">
                            <label for="forum-mod-url-image" class="forum-label"><?php echo esc_html__('Related mod URL (optional)', 'gta6mods'); ?></label>
                            <div class="forum-input-icon">
                                <i class="fas fa-link"></i>
                                <input type="url" id="forum-mod-url-image" class="forum-input" data-field="mod-url" placeholder="https://www.gta6-mods.com/vehicles/...">
                            </div>
                        </div>

                        <div class="forum-field">
                            <label class="forum-label"><?php echo esc_html__('Image source', 'gta6mods'); ?></label>
                            <div class="forum-image-source" data-image-source>
                                <button type="button" class="forum-image-source__button active" data-image-source-option="upload"><?php echo esc_html__('Upload file', 'gta6mods'); ?></button>
                                <button type="button" class="forum-image-source__button" data-image-source-option="url"><?php echo esc_html__('Use URL', 'gta6mods'); ?></button>
                            </div>

                            <div class="forum-image-panel" data-image-source-panel="upload">
                                <input type="file" id="forum-image-file" class="forum-upload__input" data-field="image-file" accept="image/*" hidden>
                                <label for="forum-image-file" class="forum-upload-box" data-upload-zone>
                                    <div class="image-upload-box">
                                        <i class="fas fa-cloud-upload-alt fa-3x mb-2 text-gray-400" aria-hidden="true"></i>
                                        <p class="font-semibold text-gray-700"><?php echo esc_html__('Click to upload', 'gta6mods'); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo esc_html__('or drag the file here', 'gta6mods'); ?></p>
                                        <p class="text-xs text-gray-500 mt-2"><?php echo esc_html__('PNG, JPG, GIF, WebP (max. 5MB)', 'gta6mods'); ?></p>
                                        <p class="forum-upload__filename" data-upload-filename></p>
                                    </div>
                                </label>
                            </div>
                            <div class="forum-image-panel hidden" data-image-source-panel="url" aria-hidden="true">
                                <label for="forum-image-url" class="forum-label"><?php echo esc_html__('Image URL*', 'gta6mods'); ?></label>
                                <input type="url" id="forum-image-url" class="forum-input" data-field="image-url" placeholder="https://example.com/screenshot.jpg">
                                <p class="forum-help-text"><?php echo esc_html__('Remote images will be embedded directly into your thread.', 'gta6mods'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="forum-create__panel<?php echo $is_link_active ? '' : ' hidden'; ?>" data-tab-panel="link" aria-hidden="<?php echo $is_link_active ? 'false' : 'true'; ?>">
                        <div class="forum-field">
                            <label for="forum-title-link" class="forum-label"><?php echo esc_html__('Title*', 'gta6mods'); ?></label>
                            <input type="text" id="forum-title-link" class="forum-input" data-field="title" placeholder="<?php echo esc_attr__('Summarise the link…', 'gta6mods'); ?>">
                        </div>

                        <div class="forum-field">
                            <label for="forum-link" class="forum-label"><?php echo esc_html__('Link URL*', 'gta6mods'); ?></label>
                            <div class="forum-input-icon">
                                <i class="fas fa-globe"></i>
                                <input type="url" id="forum-link" class="forum-input" data-field="link" placeholder="https://example.com/article">
                            </div>
                        </div>

                        <div class="forum-field">
                            <label for="forum-mod-url-link" class="forum-label"><?php echo esc_html__('Related mod URL (optional)', 'gta6mods'); ?></label>
                            <div class="forum-input-icon">
                                <i class="fas fa-link"></i>
                                <input type="url" id="forum-mod-url-link" class="forum-input" data-field="mod-url" placeholder="https://www.gta6-mods.com/vehicles/...">
                            </div>
                        </div>
                    </div>

                    <div class="forum-field forum-field--flairs">
                        <label class="forum-label"><?php echo esc_html__('Flair (pick one)', 'gta6mods'); ?></label>
                        <div class="forum-flair-picker" data-flair-options></div>
                        <input type="hidden" name="flair" data-selected-flair>
                    </div>

                    <div class="forum-create__actions">
                        <a href="<?php echo esc_url($forum_home_url); ?>" class="forum-button forum-button--secondary"><?php echo esc_html__('Cancel', 'gta6mods'); ?></a>
                        <button type="submit" class="forum-button forum-button--primary" data-submit disabled>
                            <span class="forum-button__label"><?php echo esc_html__('Publish thread', 'gta6mods'); ?></span>
                        </button>
                    </div>

                    <p class="forum-create__status" data-create-status></p>
                </form>
            </div>
        </div>

        <aside class="col-span-12 lg:col-span-4 forum-create__aside">
            <div class="sticky top-6 forum-create__aside-inner">
                <div class="card forum-create__tips">
                    <div class="p-4 border-b forum-create__tips-header">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2 forum-create__tips-title">
                            <i class="fas fa-scroll"></i> <?php echo esc_html__('Posting tips', 'gta6mods'); ?>
                        </h3>
                    </div>
                    <div class="p-4 text-sm text-gray-600 space-y-3 forum-create__tips-body">
                        <p><strong><?php echo esc_html__('Keep it relevant.', 'gta6mods'); ?></strong> <?php echo esc_html__('Ensure your thread matches the flair and contributes to the discussion.', 'gta6mods'); ?></p>
                        <p><strong><?php echo esc_html__('Cite your sources.', 'gta6mods'); ?></strong> <?php echo esc_html__('Link to trusted references when sharing news or leaks.', 'gta6mods'); ?></p>
                        <p><strong><?php echo esc_html__('Be descriptive.', 'gta6mods'); ?></strong> <?php echo esc_html__('Clear titles and summaries help others quickly understand your post.', 'gta6mods'); ?></p>
                        <p><strong><?php echo esc_html__('Respect others.', 'gta6mods'); ?></strong> <?php echo esc_html__('Follow the community guidelines and report any abusive behaviour.', 'gta6mods'); ?></p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<?php
get_footer();
