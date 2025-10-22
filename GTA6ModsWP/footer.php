<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<footer class="bg-gray-800 text-white mt-12">
    <div class="container mx-auto px-6 py-8">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div>
                <h4 class="font-bold mb-3 text-white">GTA6-Mods</h4>
                <p class="text-sm text-gray-400"><?php bloginfo('description'); ?></p>
            </div>
            <div>
                <h4 class="font-bold mb-3 text-white"><?php esc_html_e('Navigáció', 'gta6-mods'); ?></h4>
                <ul class="text-sm space-y-2">
                    <li><a href="#" class="text-gray-400 hover:text-white"><?php esc_html_e('Modok', 'gta6-mods'); ?></a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white"><?php esc_html_e('Fórum', 'gta6-mods'); ?></a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white"><?php esc_html_e('Hírek', 'gta6-mods'); ?></a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-3 text-white"><?php esc_html_e('Információ', 'gta6-mods'); ?></h4>
                <ul class="text-sm space-y-2">
                    <li><a href="#" class="text-gray-400 hover:text-white"><?php esc_html_e('Rólunk', 'gta6-mods'); ?></a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white"><?php esc_html_e('Kapcsolat', 'gta6-mods'); ?></a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white"><?php esc_html_e('Adatvédelem', 'gta6-mods'); ?></a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold mb-3 text-white"><?php esc_html_e('Közösség', 'gta6-mods'); ?></h4>
                <div class="flex space-x-4 text-xl">
                    <a href="#" class="text-gray-400 hover:text-white" aria-label="Discord"><i class="fa-brands fa-discord"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#" class="text-gray-400 hover:text-white" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>
        </div>
        <div class="text-center text-gray-500 text-sm mt-8 border-t border-gray-700 pt-6">
            &copy; <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?>. <?php esc_html_e('Nem áll kapcsolatban a Rockstar Games-szel.', 'gta6-mods'); ?>
        </div>
    </div>
</footer>

<div id="gta6-giphy-modal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-xl shadow-2xl p-4 w-full max-w-lg h-3/4 flex flex-col">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800"><?php esc_html_e('Search GIFs', 'gta6-mods'); ?></h3>
            <button type="button" id="gta6-giphy-close" class="text-gray-400 hover:text-gray-800 transition">
                <i class="fas fa-times fa-lg"></i>
            </button>
        </div>
        <div class="mb-4">
            <label for="gta6-giphy-search" class="sr-only"><?php esc_html_e('Search GIPHY', 'gta6-mods'); ?></label>
            <input id="gta6-giphy-search" type="text" placeholder="<?php esc_attr_e('Search GIPHY...', 'gta6-mods'); ?>" class="w-full p-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-pink-500 focus:border-transparent outline-none">
        </div>
        <div id="gta6-giphy-results" class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 gap-2"></div>
        <div class="text-xs text-center mt-2 text-gray-400"><?php esc_html_e('Powered by GIPHY', 'gta6-mods'); ?></div>
    </div>
</div>

<div id="gta6-mention-suggestions" class="hidden absolute bg-white border border-gray-200 rounded-md shadow-lg z-50 w-64"></div>

<?php wp_footer(); ?>
</body>
</html>
